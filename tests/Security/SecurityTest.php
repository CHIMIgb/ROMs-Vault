<?php
/**
 * tests/Security/SecurityTest.php
 * Suite Security (sección 5 del plan de mejoras): pruebas de integración que
 * miden el nivel de seguridad real de la aplicación contra el servidor HTTP.
 *
 * Reutiliza el server PHP real (php -S + router.php) y la BD de prueba
 * (roms-vault-test) mediante IntegrationTestCase.
 *
 * 5.3 (rate limit de login + lockout) y 5.9 (host allowlist del proxy) quedan
 * cubiertos por AuthFlowTest y GDriveAllowlistTest (Unit) respectivamente.
 */

namespace Tests\Security;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Server;
use UrlSigner;

require_once dirname(__DIR__, 2) . '/src/config/UrlSigner.php';

class SecurityTest extends IntegrationTestCase {

    /**
     * Secret del SERVIDOR de integración (tests/Integration/Server.php).
     * NO usar $_ENV['JWT_SECRET'] aquí: la suite Unit lo deja con otro valor
     * (UrlSignerTest y TfaServiceTest lo reasignan) y la firma fallaría al
     * correr la suite completa. La firma la valida el server, no PHPUnit.
     */
    private const SERVER_JWT_SECRET = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';

    // ── 5.1 Accesos sin sesión ──────────────────────────────────────────────
    public function testAdminDashboardSinSesionRedirigeAlLogin(): void {
        Server::resetCookies();
        $resp = $this->get('/admin/dashboard');
        $this->assertSame(302, $resp['status']);
        $this->assertSame('/auth/login', $resp['headers']['location'] ?? '');
    }

    public function testAjaxAdminSinSesionResponde403(): void {
        Server::resetCookies();
        foreach (['ajax_admin.php', 'ajax_consola.php', 'ajax_categoria.php', 'ajax_emulador.php'] as $ep) {
            $resp = $this->get('/' . $ep);
            $this->assertSame(403, $resp['status'], $ep . ' debe responder 403 sin sesión');
            $this->assertStringContainsString('Acceso denegado', $resp['body'], $ep);
        }
    }

    // ── 5.2 CSRF ────────────────────────────────────────────────────────────
    public function testPostSinTokenCsrfResponde403(): void {
        Server::resetCookies();
        $this->get('/auth/login'); // captura cookie rv_csrf
        // Server::post directo (sin csrf_token automático a propósito)
        $resp = Server::post('/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);
        $this->assertSame(403, $resp['status']);
        $this->assertStringContainsString('Token CSRF', $resp['body']);
    }

    public function testPostConTokenCsrfInvalidoResponde403(): void {
        Server::resetCookies();
        $this->get('/auth/login');
        $resp = Server::post('/auth/login', [
            'username'   => 'admin',
            'password'   => 'admin123',
            'csrf_token' => str_repeat('0', 64), // token falso
        ]);
        $this->assertSame(403, $resp['status']);
    }

    // ── 5.4 Firma de enlaces (rom_proxy.php) ────────────────────────────────
    public function testProxySinFirmaRechazado403(): void {
        Server::resetCookies();
        $resp = $this->get('/rom_proxy.php?file_id=file-sin-firma');
        $this->assertSame(403, $resp['status']);
    }

    public function testProxyFirmaTamperRechazada403(): void {
        Server::resetCookies();
        $t   = time();
        $sig = hash_hmac('sha256', 'file-tamper|' . $t, self::SERVER_JWT_SECRET);
        $resp = $this->get('/rom_proxy.php?file_id=file-tamper&t=' . $t . '&sig=' . strrev($sig));
        $this->assertSame(403, $resp['status']);
    }

    public function testProxyFirmaVencidaRechazada410(): void {
        Server::resetCookies();
        $t   = time() - 3600; // fuera de TTL (900 s)
        $sig = hash_hmac('sha256', 'file-vencida|' . $t, self::SERVER_JWT_SECRET);
        $resp = $this->get('/rom_proxy.php?file_id=file-vencida&t=' . $t . '&sig=' . $sig);
        // 410 Gone = enlace expirado (403 = no autorizado/incorrecto, 404 = no existe)
        $this->assertSame(410, $resp['status']);
    }

    public function testProxyFirmaValidaConFileInexistente404(): void {
        Server::resetCookies();
        $url = UrlSigner::proxyUrl('archivo-fake-que-no-existe');
        $resp = $this->get($url);
        $this->assertSame(404, $resp['status'], 'Firma válida pero archivo no existe en la BD → 404.');
    }

    // ── 5.5 /src/* nunca servible ───────────────────────────────────────────
    public function testSrcNoServiblePorHttp(): void {
        Server::resetCookies();
        foreach (['/src/config/database.php', '/src/models/Juego.php', '/src'] as $path) {
            $resp = $this->get($path);
            $this->assertSame(403, $resp['status'], $path . ' debe responder 403');
        }
    }

    // ── 5.6 Método no permitido / validación / JSON coherente ───────────────
    public function testIdNoNumericoDevuelve404(): void {
        Server::resetCookies();
        // Antes del fix, un id no numérico rompía con PDOException fatal
        foreach (['/home/show/abc', '/home/show/1%22%3E%3Cscript%3E'] as $path) {
            $resp = $this->get($path);
            $this->assertSame(404, $resp['status'], $path . ' debe responder 404, no fatal.');
            $this->assertStringNotContainsString('PDOException', $resp['body']);
        }
    }

    public function testControladorNoAlfanumericoDevuelve404(): void {
        Server::resetCookies();
        $resp = $this->get('/<script>/index');
        $this->assertSame(404, $resp['status']);
    }

    public function testAjaxAutocompleteRespondeJsonCoherente(): void {
        Server::resetCookies();
        $resp = $this->get('/ajax_autocomplete.php?q=zelda');
        $this->assertSame(200, $resp['status']);
        $json = json_decode($resp['body'], true);
        $this->assertIsArray($json, 'El body debe ser JSON de array (aunque sea vacío).');
    }

    // ── 5.7 Intento de inyección SQL ────────────────────────────────────────
    public function testBusquedaSqlInjectionNoProduceErrorNiResultadosAnomalos(): void {
        Server::resetCookies();
        $resp = $this->get('/ajax_catalog.php?busqueda=' . urlencode("' OR 1=1 --"));
        $this->assertSame(200, $resp['status']);
        $this->assertStringNotContainsString('SQLSTATE', $resp['body']);
        $this->assertStringNotContainsString('Fatal error', $resp['body']);
    }

    public function testLoginSqlInjectionNoAutoriza(): void {
        Server::resetCookies();
        $this->get('/auth/login'); // captura cookie rv_csrf
        $resp = $this->post('/auth/login', [
            'username' => "' OR '1'='1",
            'password' => 'cualquier-cosa',
        ]);
        $this->assertSame(200, $resp['status'], 'Un login inyectado no debe redirigir (302).');
        $this->assertArrayNotHasKey('location', $resp['headers']);
    }

    // ── 5.8 Cabeceras de seguridad ──────────────────────────────────────────
    public function testCabecerasDeSeguridadPresentesEnHome(): void {
        Server::resetCookies();
        $resp = $this->get('/');
        $this->assertSame(200, $resp['status']);

        $headers = $resp['headers'];
        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? '');
        $this->assertSame('SAMEORIGIN', $headers['x-frame-options'] ?? '');
        $this->assertSame('strict-origin-when-cross-origin', $headers['referrer-policy'] ?? '');

        $csp = $headers['content-security-policy'] ?? '';
        $this->assertNotEmpty($csp, 'CSP debe estar presente.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        // NO apretar el CSP: EmulatorJS necesita unsafe-eval y blob:
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('blob:', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }
}