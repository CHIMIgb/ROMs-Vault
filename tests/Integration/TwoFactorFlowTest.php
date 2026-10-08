<?php
/**
 * tests/Integration/TwoFactorFlowTest.php
 * Flujo completo de la verificación en dos pasos (TOTP) vía HTTP real:
 * login con 2FA habilitado → segundo factor → sesión; rechazo de código
 * incorrecto; y defensa en profundidad (JWT sin claim tfa bloqueado).
 *
 * Usa el usuario de prueba 'admin_tfa' (seed de test_seeds.sql) con un
 * secreto TOTP conocido para poder calcular el código con TfaService.
 */

namespace Tests\Integration;

use TfaService;

class TwoFactorFlowTest extends IntegrationTestCase {

    private const TFA_USER   = 'admin_tfa';
    private const TFA_PASS   = 'admin123';
    private const TFA_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testLoginCon2faRedirigeAlSegundoFactorSinEmitirSesion(): void {
        $this->loginPendiente();

        // Aún no hay token de sesión (solo cookie pendiente rv_tfa_pending)
        $this->assertSame('', Server::sessionToken());
    }

    public function testSegundoFactorSinEstadoPendienteRedirigeAlLogin(): void {
        Server::resetCookies();
        $resp = $this->get('/auth/twoFactor');

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/auth/login', $resp['headers']['location'] ?? '');
    }

    public function testSegundoFactorRechazaCodigoIncorrecto(): void {
        $this->loginPendiente();

        $resp = $this->get('/auth/twoFactor');
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Código de verificación', $resp['body']);

        $resp = $this->post('/auth/twoFactor', ['code' => '000000']);
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Código incorrecto', $resp['body']);
        $this->assertSame('', Server::sessionToken());

        // El código fallido queda en la auditoría (BD fuente de verdad)
        $this->assertAuthLogContains('tfa_failed');
    }

    public function testSegundoFactorExitosoEmiteSesionYAccedeAlPanel(): void {
        $this->loginPendiente();
        $this->get('/auth/twoFactor');

        $code = TfaService::codigo(self::TFA_SECRET);
        $resp = $this->post('/auth/twoFactor', ['code' => $code]);

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/admin/dashboard', $resp['headers']['location'] ?? '');
        $this->assertNotSame('', Server::sessionToken());

        // La sesión ya vale para el panel (el claim tfa=true está presente)
        $resp = $this->get('/admin/dashboard');
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Panel de Administracion', $resp['body']);
    }

    public function testTokenSinClaimTfaDeCuentaCon2faEsRechazadoPorElPanel(): void {
        $pdo = $this->pdo();

        // 1) Quitar el 2FA temporalmente para poder obtener un JWT sin claim tfa
        $pdo->exec(
            "UPDATE public.usuarios SET tfa_enabled = FALSE, tfa_secret = NULL WHERE username = '" . self::TFA_USER . "'"
        );

        try {
            // 2) Login normal (sin 2FA): emite JWT con tfa=false
            Server::resetCookies();
            $this->get('/auth/login');
            $resp = $this->post('/auth/login', [
                'username' => self::TFA_USER,
                'password' => self::TFA_PASS,
            ]);
            $this->assertSame(302, $resp['status']);
            $this->assertNotSame('', Server::sessionToken());

            // 3) Reactivar el 2FA: la sesión emitida queda obsoleta
            $pdo->exec(
                "UPDATE public.usuarios SET tfa_enabled = TRUE, tfa_secret = '" . self::TFA_SECRET . "' "
                . "WHERE username = '" . self::TFA_USER . "'"
            );

            // 4) El panel debe rechazarlo y redirigir al segundo factor
            $resp = $this->get('/admin/dashboard');
            $this->assertSame(302, $resp['status']);
            $this->assertSame('/auth/twoFactor', $resp['headers']['location'] ?? '');
        } finally {
            // Restaurar el estado del seed para no contaminar otros tests
            $pdo->exec(
                "UPDATE public.usuarios SET tfa_enabled = TRUE, tfa_secret = '" . self::TFA_SECRET . "' "
                . "WHERE username = '" . self::TFA_USER . "'"
            );
            Server::resetCookies();
        }
    }

    /**
     * Login del usuario con 2FA: la contraseña correcta debe redirigir al
     * segundo factor (302 /auth/twoFactor) sin sesión completa.
     */
    private function loginPendiente(): void {
        Server::resetCookies();
        $this->get('/auth/login');
        $resp = $this->post('/auth/login', [
            'username' => self::TFA_USER,
            'password' => self::TFA_PASS,
        ]);

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/auth/twoFactor', $resp['headers']['location'] ?? '');
    }

    /**
     * Verifica que el evento quedó persistido en la tabla de auditoría de la
     * BD de prueba (misma estrategia que AuthFlowTest).
     */
    private function assertAuthLogContains(string $event): void {
        $row = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM public.auditoria WHERE evento = :evento'
        );
        $row->execute(['evento' => $event]);
        $this->assertGreaterThan(
            0,
            (int) $row->fetchColumn(),
            "La tabla auditoria debe contener el evento $event"
        );
    }
}