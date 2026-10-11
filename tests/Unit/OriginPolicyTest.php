<?php
/**
 * tests/Unit/OriginPolicyTest.php
 * Unit tests de la política de origen del proxy (config/OriginPolicy.php),
 * ítem 3.3 — validación opcional de Origin/Referer (modo anti-hotlink).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use OriginPolicy;

class OriginPolicyTest extends TestCase {

    private string $envOriginal;

    protected function setUp(): void {
        parent::setUp();
        $this->envOriginal = $_ENV['ALLOWED_ORIGINS'] ?? '__SIN_SET__';
    }

    protected function tearDown(): void {
        if ($this->envOriginal === '__SIN_SET__') {
            unset($_ENV['ALLOWED_ORIGINS']);
        } else {
            $_ENV['ALLOWED_ORIGINS'] = $this->envOriginal;
        }
        parent::tearDown();
    }

    public function testSinAllowlistNoValidaNada(): void {
        unset($_ENV['ALLOWED_ORIGINS']);

        $this->assertTrue(OriginPolicy::verificar('https://evil.com', null));
        $this->assertTrue(OriginPolicy::verificar(null, 'https://evil.com/page'));
        $this->assertTrue(OriginPolicy::verificar(null, null));
    }

    public function testSinHeadersSePermiteModoAntiHotlink(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        // Descarga con <a rel="noopener noreferrer"> / wget / curl firmado
        $this->assertTrue(OriginPolicy::verificar(null, null));
    }

    public function testOriginPermitidoPasa(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        $this->assertTrue(OriginPolicy::verificar('https://roms-vault.vercel.app', null));
    }

    public function testOriginAjenoSeRechaza(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        $this->assertFalse(OriginPolicy::verificar('https://evil.com', null));
    }

    public function testRefererPermitidoPasa(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        $this->assertTrue(OriginPolicy::verificar(null, 'https://roms-vault.vercel.app/juegos/1'));
    }

    public function testRefererAjenoSeRechaza(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        $this->assertFalse(OriginPolicy::verificar(null, 'https://evil.com/robos/rom.html'));
    }

    public function testBastaQueUnHeaderCoincida(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        // Origin ajeno pero Referer del sitio → permitir (Referer es fiable)
        $this->assertTrue(OriginPolicy::verificar('https://evil.com', 'https://roms-vault.vercel.app/juegos/1'));
    }

    public function testOriginNullSeRechaza(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://roms-vault.vercel.app';

        $this->assertFalse(OriginPolicy::verificar('null', null));
        $this->assertFalse(OriginPolicy::verificar('null', 'https://roms-vault.vercel.app/page'));
    }

    public function testOriginNullSinAllowlistNoSeRechaza(): void {
        unset($_ENV['ALLOWED_ORIGINS']);
        $this->assertTrue(OriginPolicy::verificar('null', null));
    }

    public function testNormalizaPuertoPorDefecto(): void {
        // https://host:443 ≡ https://host ;  http://host:80 ≡ http://host
        $this->assertSame('https://roms-vault.vercel.app', OriginPolicy::extraerOrigen('https://roms-vault.vercel.app:443'));
        $this->assertSame('http://localhost:8000', OriginPolicy::extraerOrigen('http://localhost:8000'));
        $this->assertNotSame('https://roms-vault.vercel.app:8443', OriginPolicy::extraerOrigen('https://roms-vault.vercel.app'));
    }

    public function testHostInsensitiveAMayusculas(): void {
        $_ENV['ALLOWED_ORIGINS'] = 'https://ROMs-VAULT.vercel.app';

        $this->assertTrue(OriginPolicy::verificar('https://roms-vault.vercel.app', null));
    }

    public function testEnvComaSeparadaConEspaciosYPuerto(): void {
        $_ENV['ALLOWED_ORIGINS'] = ' https://roms-vault.vercel.app , http://localhost:8000 ';

        $this->assertSame(
            ['https://roms-vault.vercel.app', 'http://localhost:8000'],
            OriginPolicy::origenesPermitidos()
        );
        $this->assertTrue(OriginPolicy::verificar('http://localhost:8000', null));
    }

    public function testExtraerOrigenConPathSoloDevuelveOrigin(): void {
        $this->assertSame('https://sitio.ejemplo', OriginPolicy::extraerOrigen('https://sitio.ejemplo/ruta/archivo.bin?x=1'));
        $this->assertNull(OriginPolicy::extraerOrigen(''));
        $this->assertNull(OriginPolicy::extraerOrigen('no-es-una-url'));
    }
}