<?php
/**
 * tests/Unit/JWTServiceTest.php
 * Unit tests del servicio de tokens JWT (config/JWTService.php).
 */

namespace Tests\Unit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JWTService;
use PHPUnit\Framework\TestCase;

class JWTServiceTest extends TestCase {

    private const SECRET = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';

    private const USER = [
        'id'       => 7,
        'username' => 'testuser',
        'rol_id'   => 2,
    ];

    protected function setUp(): void {
        parent::setUp();
        $_ENV['JWT_SECRET'] = self::SECRET;
        $_ENV['JWT_EXPIRATION'] = '3600';
        $_ENV['JWT_REFRESH_THRESHOLD'] = '600';
        unset($_COOKIE['rv_token']);
    }

    public function testGenerateProducesDecodableToken(): void {
        $token = JWTService::generate(self::USER);

        $this->assertIsString($token);
        $payload = JWTService::decode($token);

        $this->assertIsArray($payload);
        $this->assertSame(7, $payload['sub']);
        $this->assertSame('testuser', $payload['username']);
        $this->assertSame(2, $payload['rol_id']);
    }

    public function testDecodeRejectsInvalidToken(): void {
        $this->assertNull(JWTService::decode('invalid.token.value'));
        $this->assertNull(JWTService::decode(''));
    }

    public function testDecodeRejectsTokenSignedWithWrongSecret(): void {
        $token = JWT::encode(self::USER, 'otro-secreto-distinto-tambien-largo-para-hs256', 'HS256');

        $this->assertNull(JWTService::decode($token));
    }

    public function testDecodeReturnsNullForExpiredToken(): void {
        $now = time();
        $expiredPayload = array_merge(self::USER, [
            'iat' => $now - 7200,
            'exp' => $now - 3600,
        ]);
        $token = JWT::encode($expiredPayload, self::SECRET, 'HS256');

        $this->assertNull(JWTService::decode($token));
    }

    public function testGetCurrentUserFromCookie(): void {
        $token = JWTService::generate(self::USER);
        $_COOKIE['rv_token'] = $token;

        $user = JWTService::getCurrentUser();

        $this->assertIsArray($user);
        $this->assertSame(7, $user['user_id']);
        $this->assertSame('testuser', $user['username']);
        $this->assertSame(2, $user['rol_id']);
    }

    public function testGetCurrentUserReturnsNullWithoutCookie(): void {
        $this->assertNull(JWTService::getCurrentUser());
    }

    public function testRefreshDoesNotRefreshTokenWithPlentyOfLife(): void {
        $now = time();
        $freshPayload = array_merge(self::USER, [
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        // Debe ejecutarse sin lanzar y sin renovar (no hay cookie seteada)
        JWTService::refreshIfNeeded($freshPayload);

        $this->assertArrayNotHasKey('rv_token', $_COOKIE);
    }
}
