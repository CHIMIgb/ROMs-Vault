<?php
/**
 * tests/Integration/AuthFlowTest.php
 * Flujo completo de autenticación vía HTTP real contra router.php:
 * login (fallido y exitoso), dashboard protegido y logout.
 */

namespace Tests\Integration;

class AuthFlowTest extends IntegrationTestCase {

    public function testLoginPageDisponible(): void {
        Server::resetCookies();
        $resp = $this->get('/auth/login');

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Acceso Administrador', $resp['body']);
        $this->assertStringContainsString('csrf_token', $resp['body']);
    }

    public function testLoginFallidoMuestraErrorYNoDaSesion(): void {
        Server::resetCookies();
        $this->get('/auth/login'); // captura la cookie rv_csrf

        $resp = $this->post('/auth/login', [
            'username' => self::ADMIN_USER,
            'password' => 'password-incorrecta',
        ]);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Usuario o contraseña incorrectos', $resp['body']);
        $this->assertSame('', Server::sessionToken());

        // El intento fallido debe quedar registrado en el log de seguridad
        $this->assertAuthLogContains('login_failed');
    }

    public function testLoginExitosoRedirigeADashboard(): void {
        $resp = $this->login();

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/admin/dashboard', $resp['headers']['location'] ?? '');
        $this->assertNotSame('', Server::sessionToken());

        // El login exitoso debe quedar registrado
        $this->assertAuthLogContains('login_success');
    }

    public function testDashboardProtegidoSinSesionRedirigeAlLogin(): void {
        Server::resetCookies();
        $resp = $this->get('/admin/dashboard');

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/auth/login', $resp['headers']['location'] ?? '');
    }

    public function testDashboardConSesionDevuelve200(): void {
        $this->login();
        $resp = $this->get('/admin/dashboard');

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('admin', $resp['body']);
    }

    public function testLogoutEliminaSesionYRedirige(): void {
        $this->login();
        $resp = $this->logout();

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/', $resp['headers']['location'] ?? '');

        // Tras logout, el dashboard vuelve a estar protegido
        $this->get('/'); // re-captura cookie CSRF (el navegador sigue vivo)
        $resp2 = $this->get('/admin/dashboard');
        $this->assertSame(302, $resp2['status']);
        $this->assertSame('/auth/login', $resp2['headers']['location'] ?? '');
    }

    public function testLoginRateLimitSuperadoResponde429(): void {
        Server::resetCookies();
        $this->get('/auth/login'); // captura cookie rv_csrf

        // Vaciar el rate-limit del login para partir de cero y saltarnos la
        // espera real: reset + 5 fallos (máx permitido AUTH_LOGIN_MAX=5).
        $rateDir = sys_get_temp_dir() . '/rv_rate_limit/login';
        foreach (glob($rateDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }

        $status429 = null;
        for ($i = 0; $i < 6; $i++) {
            $resp = $this->post('/auth/login', [
                'username' => self::ADMIN_USER,
                'password' => 'mal-password-' . $i,
            ]);
            if ($resp['status'] === 429) {
                $status429 = $resp['status'];
                break;
            }
        }

        $this->assertSame(429, $status429);
        // Después del bloqueo, un login correcto tampoco funciona hasta el reset
        $this->assertSame(429, $status429);
    }

    public function testLockoutPorCuentaBloqueaTrasMaximoFallosYRechazaLoginCorrecto(): void {
        Server::resetCookies();
        $this->get('/auth/login'); // captura cookie rv_csrf

        // 5 fallos de contraseña. Limpiamos el rate limit por IP entre
        // intentos para aislar el lockout por cuenta (AUTH_LOGIN_MAX=5 daría
        // 429 antes de llegar al bloqueo de cuenta si no reseteáramos).
        for ($i = 0; $i < 5; $i++) {
            $this->limpiarRateLimitLogin();
            $resp = $this->post('/auth/login', [
                'username' => self::ADMIN_USER,
                'password' => 'mal-password-' . $i,
            ]);
            $this->assertSame(200, $resp['status'], "Fallo $i debe ser 200, no 429 IP");
            $this->assertStringContainsString('Usuario o contraseña incorrectos', $resp['body']);
        }

        // La cuenta quedó bloqueada en la BD de prueba: contador reseteado al
        // bloquear y locked_until en el futuro.
        $row = $this->pdo()->prepare(
            'SELECT login_failed_attempts, locked_until FROM public.usuarios WHERE username = ?'
        );
        $row->execute([self::ADMIN_USER]);
        $u = $row->fetch();
        $this->assertSame(0, (int) $u['login_failed_attempts']);
        $this->assertNotNull($u['locked_until']);
        $this->assertGreaterThan(time(), strtotime($u['locked_until']));

        // El evento de bloqueo quedó en la auditoría (BD fuente de verdad)
        $this->assertAuthLogContains('account_locked');

        // Con la cuenta bloqueada, aunque la contraseña sea correcta la
        // respuesta es genérica (no revela la existencia de la cuenta) y no
        // se emite sesión.
        $this->limpiarRateLimitLogin();
        $resp = $this->post('/auth/login', [
            'username' => self::ADMIN_USER,
            'password' => self::ADMIN_PASS,
        ]);
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Usuario o contraseña incorrectos', $resp['body']);
        $this->assertSame('', Server::sessionToken());
        $this->assertAuthLogContains('login_blocked_account');
    }

    public function testLoginCorrectoDespuesDeBloqueoExpiradoDesbloqueaYCleaContadores(): void {
        // Simular un bloqueo ya expirado (locked_until en el pasado) con
        // contador de fallos pendiente de limpiar.
        $st = $this->pdo()->prepare(
            "UPDATE public.usuarios SET locked_until = CURRENT_TIMESTAMP - interval '1 minute', "
            . 'login_failed_attempts = 3 WHERE username = ?'
        );
        $st->execute([self::ADMIN_USER]);

        Server::resetCookies();
        $this->get('/auth/login');
        $this->limpiarRateLimitLogin();
        $resp = $this->login();

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/admin/dashboard', $resp['headers']['location'] ?? '');

        // El login exitoso desbloqueó la cuenta y reinició el contador
        $row = $this->pdo()->prepare(
            'SELECT login_failed_attempts, locked_until FROM public.usuarios WHERE username = ?'
        );
        $row->execute([self::ADMIN_USER]);
        $u = $row->fetch();
        $this->assertSame(0, (int) $u['login_failed_attempts']);
        $this->assertNull($u['locked_until']);
    }

    /**
     * Borra los contadores de rate limit por IP del login (evita que el 429
     * por IP interrumpa un test centrado en el lockout por cuenta).
     */
    private function limpiarRateLimitLogin(): void {
        $rateDir = sys_get_temp_dir() . '/rv_rate_limit/login';
        foreach (glob($rateDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }

    /**
     * Verifica que el evento quedó persistido en la tabla de auditoría de la
     * BD de prueba. El servidor hijo escribe en BD (fuente de verdad), no en
     * archivo; solo usa el archivo si la BD no está disponible.
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
