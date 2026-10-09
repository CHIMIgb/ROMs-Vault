<?php
/**
 * tests/Integration/I18nFlowTest.php
 * Integration tests de la detección de idioma (ítem 6.2).
 *
 * Comprueba el flujo HTTP real:
 * - GET /?lang=es|en → 302 con cookie rv_lang y Location sin el parámetro
 * - la cookie rv_lang se refleja en <html lang="...">
 * - ?lang= inválido se ignora (200, sin cookie)
 * - Accept-Language detecta idioma cuando no hay cookie
 * - sin indicios → base 'es'
 */

namespace Tests\Integration;

class I18nFlowTest extends IntegrationTestCase {

    public function testQueryLangEscribeCookieYRedirigeSinParametro(): void {
        Server::resetCookies();
        $resp = Server::get('/?lang=en');

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/', $resp['headers']['location'] ?? '');
        $this->assertSame('en', $resp['cookies']['rv_lang'] ?? '');
    }

    public function testQueryLangEnEsRedirigeASinParametro(): void {
        Server::resetCookies();
        $resp = Server::get('/?lang=es');

        $this->assertSame(302, $resp['status']);
        $this->assertSame('/', $resp['headers']['location'] ?? '');
        $this->assertSame('es', $resp['cookies']['rv_lang'] ?? '');
    }

    public function testQueryLangConservaOtrosParametros(): void {
        Server::resetCookies();
        $resp = Server::get('/?lang=en&page=2');
        $this->assertSame(302, $resp['status']);
        $this->assertSame('/?page=2', $resp['headers']['location'] ?? '');
    }

    public function testCookieRvLangReflejaIdiomaEnHtml(): void {
        Server::resetCookies();
        Server::get('/?lang=en'); // deja la cookie en el "navegador" de prueba
        $resp = Server::get('/');

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('<html lang="en">', $resp['body']);
    }

    public function testLangInvalidoSeIgnoraSinCookie(): void {
        Server::resetCookies();
        $resp = Server::get('/?lang=fr');

        $this->assertSame(200, $resp['status']);
        $this->assertArrayNotHasKey('rv_lang', $resp['cookies']);
    }

    public function testAcceptLanguageDetectaIdiomaSinCookie(): void {
        Server::resetCookies();
        $resp = Server::get('/auth/login', ['Accept-Language' => 'en-US,en;q=0.9,es;q=0.8']);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('<html lang="en">', $resp['body']);
    }

    public function testAcceptLanguageSoloSoportadosIgnoraResto(): void {
        Server::resetCookies();
        // fr y de no están en la whitelist (es|en) → cae al primer soportado
        $resp = Server::get('/auth/login', ['Accept-Language' => 'fr-CA,de;q=0.9,en;q=0.8']);

        $this->assertStringContainsString('<html lang="en">', $resp['body']);
    }

    public function testSinIndiciosUsaBaseEspanol(): void {
        Server::resetCookies();
        $resp = Server::get('/');

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('<html lang="es">', $resp['body']);
    }
}