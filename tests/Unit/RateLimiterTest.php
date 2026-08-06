<?php
/**
 * tests/Unit/RateLimiterTest.php
 * Unit tests del rate limiting por IP con ventana deslizante (config/RateLimiter.php).
 *
 * Los tests usan un namespace exclusivo y limpian el directorio temporal
 * para no interferir con los contadores de producción.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RateLimiter;

class RateLimiterTest extends TestCase {

    private string $namespace;

    protected function setUp(): void {
        parent::setUp();
        $this->namespace = 'test_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void {
        $dir = sys_get_temp_dir() . '/rv_rate_limit/' . $this->namespace;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        // Limpiar variables de servidor usadas por clientIp()
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        parent::tearDown();
    }

    public function testAllowsRequestsUpToLimitThenBlocks(): void {
        $ip = '203.0.113.10';

        $this->assertTrue(RateLimiter::check($ip, 3, 60, $this->namespace));
        $this->assertTrue(RateLimiter::check($ip, 3, 60, $this->namespace));
        $this->assertTrue(RateLimiter::check($ip, 3, 60, $this->namespace));
        $this->assertFalse(RateLimiter::check($ip, 3, 60, $this->namespace));
    }

    public function testResetAllowsRequestsAgain(): void {
        $ip = '203.0.113.11';

        RateLimiter::check($ip, 2, 60, $this->namespace);
        RateLimiter::check($ip, 2, 60, $this->namespace);
        $this->assertFalse(RateLimiter::check($ip, 2, 60, $this->namespace));

        RateLimiter::reset($ip, $this->namespace);

        $this->assertTrue(RateLimiter::check($ip, 2, 60, $this->namespace));
    }

    public function testExpiredWindowAllowsRequestsAgain(): void {
        $ip = '203.0.113.12';
        $file = sys_get_temp_dir() . '/rv_rate_limit/' . $this->namespace . '/' . md5($ip) . '.json';

        // Escribir un contador con ventana ya vencida (hace 5 minutos)
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        file_put_contents($file, json_encode([
            'window_start' => time() - 300,
            'count'        => 999,
        ]));

        $this->assertTrue(RateLimiter::check($ip, 3, 60, $this->namespace));
    }

    public function testSeparateNamespacesDoNotMixCounters(): void {
        $ip = '203.0.113.13';
        $otherNamespace = $this->namespace . '_other';

        RateLimiter::check($ip, 1, 60, $this->namespace);
        $this->assertFalse(RateLimiter::check($ip, 1, 60, $this->namespace));
        // Otro namespace no tiene contador, debe permitir
        $this->assertTrue(RateLimiter::check($ip, 1, 60, $otherNamespace));
    }

    public function testClientIpUsesRemoteAddrForPublicIp(): void {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $this->assertSame('8.8.8.8', RateLimiter::clientIp());
    }

    public function testClientIpTrustsForwardedFromLocalProxy(): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.1.2.3';
        $this->assertSame('200.1.2.3', RateLimiter::clientIp());
    }

    public function testClientIpIgnoresSpoofedForwardedFromPublicIp(): void {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6';
        $this->assertSame('8.8.8.8', RateLimiter::clientIp());
    }

    public function testClientIpFallbackWhenRemoteAddrInvalid(): void {
        $_SERVER['REMOTE_ADDR'] = 'no-es-una-ip';
        $this->assertSame('0.0.0.0', RateLimiter::clientIp());
    }
}
