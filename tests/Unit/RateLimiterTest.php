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
        // Limpiar variables de servidor y entorno usadas por clientIp()
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        unset($_ENV['TRUSTED_PROXIES']);
        parent::tearDown();
    }

    /** Define la allowlist TRUSTED_PROXIES (null = deny-by-default). */
    private function setTrustedProxies(?string $value): void {
        if ($value === null) {
            unset($_ENV['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $value;
        }
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
        $this->setTrustedProxies('127.0.0.1,::1');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.1.2.3';
        $this->assertSame('200.1.2.3', RateLimiter::clientIp());
    }

    public function testClientIpIgnoresSpoofedForwardedFromPublicIp(): void {
        $this->setTrustedProxies('127.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6';
        $this->assertSame('8.8.8.8', RateLimiter::clientIp());
    }

    public function testClientIpDeniesAllWhenTrustedProxiesEmpty(): void {
        // Sin TRUSTED_PROXIES (deny-by-default) ni siquiera 127.0.0.1 es proxy.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.1.2.3';
        $this->assertSame('127.0.0.1', RateLimiter::clientIp());
    }

    public function testClientIpTrustsForwardedFromCidrNetwork(): void {
        $this->setTrustedProxies('10.0.0.0/8');
        $_SERVER['REMOTE_ADDR'] = '10.1.2.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
        $this->assertSame('203.0.113.5', RateLimiter::clientIp());

        // IP fuera de la red CIDR → no confía
        $_SERVER['REMOTE_ADDR'] = '11.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.6';
        $this->assertSame('11.0.0.1', RateLimiter::clientIp());
    }

    public function testClientIpTrustsForwardedFromIpv6Allowlist(): void {
        $this->setTrustedProxies('::1,2001:db8::/32');
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $this->assertSame('203.0.113.7', RateLimiter::clientIp());

        // IPv6 fuera de la red → no confía
        $_SERVER['REMOTE_ADDR'] = '2001:db9::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.8';
        $this->assertSame('2001:db9::1', RateLimiter::clientIp());
    }

    public function testClientIpIgnoresInvalidForwardedFromTrustedProxy(): void {
        $this->setTrustedProxies('127.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'no-es-una-ip';
        $this->assertSame('127.0.0.1', RateLimiter::clientIp());
    }

    public function testClientIpFallbackWhenRemoteAddrInvalid(): void {
        $_SERVER['REMOTE_ADDR'] = 'no-es-una-ip';
        $this->assertSame('0.0.0.0', RateLimiter::clientIp());
    }
}
