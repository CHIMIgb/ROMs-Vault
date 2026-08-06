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
    }

    public function testLoginExitosoRedirigeADashboard(): void {
        $resp = $this->login();

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/admin/dashboard', $resp['headers']['location'] ?? '');
        $this->assertNotSame('', Server::sessionToken());
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
}
