<?php
/**
 * tests/Unit/CsrfServiceTest.php
 * Unit tests de la protección CSRF "Double Submit Cookie" (config/CsrfService.php).
 *
 * Los tests manipulan $_COOKIE / $_POST / $_GET / $_SERVER directamente y
 * resetean el token estático entre cada test mediante Reflection.
 */

namespace Tests\Unit;

use CsrfService;
use PHPUnit\Framework\TestCase;

class CsrfServiceTest extends TestCase {

    /** Token de 64 hex que simulamos que el navegador ya tiene en la cookie */
    private const VALID_COOKIE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void {
        parent::setUp();
        $_ENV['JWT_SECRET'] = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';

        // Resetear el estado estático del servicio
        $prop = new \ReflectionProperty(CsrfService::class, 'token');
        $prop->setValue(null);

        // Entorno limpio
        unset($_COOKIE['rv_csrf'], $_POST['csrf_token'], $_GET['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    protected function tearDown(): void {
        unset($_COOKIE['rv_csrf'], $_POST['csrf_token'], $_GET['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
        parent::tearDown();
    }

    public function testEnsureTokenReusesExistingValidCookie(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;

        $token = CsrfService::ensureToken();

        $this->assertSame(self::VALID_COOKIE, $token);
    }

    public function testEnsureTokenGeneratesNewOneWhenNoCookie(): void {
        $token = CsrfService::ensureToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testEnsureTokenRejectsMalformedCookie(): void {
        $_COOKIE['rv_csrf'] = 'short';

        $token = CsrfService::ensureToken();

        $this->assertNotSame('short', $token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function testVerifyAcceptsPostToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;
        $_POST['csrf_token'] = self::VALID_COOKIE;

        $this->assertTrue(CsrfService::verify());
    }

    public function testVerifyRejectsWrongPostToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;
        $_POST['csrf_token'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        $this->assertFalse(CsrfService::verify());
    }

    public function testVerifyAcceptsHeaderToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::VALID_COOKIE;

        $this->assertTrue(CsrfService::verify());
    }

    public function testVerifyAcceptsQueryToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;
        $_GET['csrf_token'] = self::VALID_COOKIE;

        $this->assertTrue(CsrfService::verify());
    }

    public function testVerifyRejectsMissingToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;

        $this->assertFalse(CsrfService::verify());
    }

    public function testVerifyAjaxAcceptsHeader(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::VALID_COOKIE;

        $this->assertTrue(CsrfService::verifyAjax());
    }

    public function testVerifyAjaxRejectsMissingHeader(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;

        $this->assertFalse(CsrfService::verifyAjax());
    }

    public function testFieldContainsToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;

        $field = CsrfService::field();

        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString(self::VALID_COOKIE, $field);
    }

    public function testMetaTagContainsToken(): void {
        $_COOKIE['rv_csrf'] = self::VALID_COOKIE;

        $meta = CsrfService::metaTag();

        $this->assertStringContainsString('name="csrf-token"', $meta);
        $this->assertStringContainsString(self::VALID_COOKIE, $meta);
    }
}
