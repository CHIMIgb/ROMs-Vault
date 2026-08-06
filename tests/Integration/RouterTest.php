<?php
/**
 * tests/Integration/RouterTest.php
 * Verifica el enrutador real (router.php → index.php) con URLs limpias y
 * retrocompatibilidad (index.php?controller=...&action=...).
 */

namespace Tests\Integration;

class RouterTest extends IntegrationTestCase {

    public function testRutaRaizDevuelve200(): void {
        Server::resetCookies();
        $resp = $this->get('/');
        $this->assertSame(200, $resp['status']);
    }

    public function testRutaLoginDevuelve200(): void {
        Server::resetCookies();
        $resp = $this->get('/auth/login');
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Acceso Administrador', $resp['body']);
    }

    public function testRutaAdminProtegidaRedirigeSinSesion(): void {
        Server::resetCookies();
        $resp = $this->get('/consola/index');
        $this->assertSame(302, $resp['status']);
        $this->assertSame('/auth/login', $resp['headers']['location'] ?? '');
    }

    public function testRetrocompatibilidadQueryString(): void {
        Server::resetCookies();
        $resp = $this->get('/index.php?controller=home&action=index');
        $this->assertSame(200, $resp['status']);
    }

    public function testControladorInexistenteDevuelve404(): void {
        Server::resetCookies();
        $resp = $this->get('/noexiste/index');
        $this->assertSame(404, $resp['status']);
    }

    public function testAccionInexistenteDevuelve404(): void {
        Server::resetCookies();
        $resp = $this->get('/home/accion-inexistente');
        $this->assertSame(404, $resp['status']);
    }

    public function testSegundoSegmentoComoIdEnUrlLimpia(): void {
        Server::resetCookies();
        // /home/show/999999 → HomeController::show(999999) → juego inexistente → 404
        $resp = $this->get('/home/show/999999');
        $this->assertSame(404, $resp['status']);
    }
}
