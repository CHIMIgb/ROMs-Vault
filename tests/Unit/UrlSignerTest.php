<?php
/**
 * tests/Unit/UrlSignerTest.php
 * Unit tests del firmado HMAC de URLs sensibles (config/UrlSigner.php).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use UrlSigner;

class UrlSignerTest extends TestCase {

    private const FILE_ID = '1AbCdEfGh1234567890';

    protected function setUp(): void {
        parent::setUp();
        $_ENV['JWT_SECRET'] = 'secret-de-prueba-phpunit-2026';
    }

    public function testSignAndVerifyRoundtrip(): void {
        $params = UrlSigner::sign(self::FILE_ID);

        $this->assertIsInt($params['t']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $params['sig']);
        $this->assertTrue(UrlSigner::verify(self::FILE_ID, $params['t'], $params['sig']));
    }

    public function testVerifyRejectsTamperedSignature(): void {
        $params = UrlSigner::sign(self::FILE_ID);

        // Cambiar un solo carácter de la firma
        $tampered = $params['sig'];
        $tampered[0] = $tampered[0] === 'a' ? 'b' : 'a';

        $this->assertFalse(UrlSigner::verify(self::FILE_ID, $params['t'], $tampered));
    }

    public function testVerifyRejectsExpiredSignature(): void {
        $timestamp = time() - UrlSigner::TTL - 1;
        $sig = hash_hmac('sha256', self::FILE_ID . '|' . $timestamp, $_ENV['JWT_SECRET']);

        $this->assertFalse(UrlSigner::verify(self::FILE_ID, $timestamp, $sig));
    }

    public function testVerifyRejectsFutureTimestamp(): void {
        // Firma con timestamp 301s en el futuro (fuera del skew de 300s)
        $timestamp = time() + 301;
        $sig = hash_hmac('sha256', self::FILE_ID . '|' . $timestamp, $_ENV['JWT_SECRET']);

        $this->assertFalse(UrlSigner::verify(self::FILE_ID, $timestamp, $sig));
    }

    public function testVerifyRejectsEmptySignatureOrInvalidTimestamp(): void {
        $params = UrlSigner::sign(self::FILE_ID);

        $this->assertFalse(UrlSigner::verify(self::FILE_ID, $params['t'], ''));
        $this->assertFalse(UrlSigner::verify(self::FILE_ID, 0, $params['sig']));
        $this->assertFalse(UrlSigner::verify(self::FILE_ID, -5, $params['sig']));
    }

    public function testDownloadUrlFormat(): void {
        $url = UrlSigner::downloadUrl(self::FILE_ID);

        $this->assertStringStartsWith('/home/download?file_id=', $url);
        $this->assertStringContainsString('file_id=' . urlencode(self::FILE_ID), $url);
        $this->assertStringContainsString('&t=', $url);
        $this->assertStringContainsString('&sig=', $url);
    }

    public function testProxyUrlFormat(): void {
        $url = UrlSigner::proxyUrl(self::FILE_ID);

        $this->assertStringStartsWith('/rom_proxy.php?file_id=', $url);
        $this->assertStringContainsString('file_id=' . urlencode(self::FILE_ID), $url);
        $this->assertStringContainsString('&t=', $url);
        $this->assertStringContainsString('&sig=', $url);
    }

    public function testSignThrowsWithoutSecret(): void {
        $original = $_ENV['JWT_SECRET'] ?? null;
        unset($_ENV['JWT_SECRET']);

        try {
            $this->expectException(RuntimeException::class);
            UrlSigner::sign(self::FILE_ID);
        } finally {
            if ($original !== null) {
                $_ENV['JWT_SECRET'] = $original;
            }
        }
    }
}
