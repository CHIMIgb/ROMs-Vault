<?php
/**
 * tests/Unit/GDriveAllowlistTest.php
 * Unit tests de la allowlist de hosts del proxy (config/GDriveAllowlist.php),
 * ítem 3.2 — anti-SSRF: solo se siguen redirecciones a hosts de Google Drive.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use GDriveAllowlist;

class GDriveAllowlistTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->envOriginal = $_ENV['GDRIVE_ALLOWED_HOSTS'] ?? '__SIN_SET__';
    }

    protected function tearDown(): void {
        if ($this->envOriginal === '__SIN_SET__') {
            unset($_ENV['GDRIVE_ALLOWED_HOSTS']);
        } else {
            $_ENV['GDRIVE_ALLOWED_HOSTS'] = $this->envOriginal;
        }
        parent::tearDown();
    }

    private string $envOriginal;

    public function testHostsPermitidosUsaDefaultsSinEnv(): void {
        unset($_ENV['GDRIVE_ALLOWED_HOSTS']);

        $this->assertSame(GDriveAllowlist::HOSTS_DEFAULT, GDriveAllowlist::hostsPermitidos());
        $this->assertContains('drive.google.com', GDriveAllowlist::hostsPermitidos());
        $this->assertContains('drive.usercontent.google.com', GDriveAllowlist::hostsPermitidos());
    }

    public function testHostsPermitidosTrataEnvVacioComoDefaults(): void {
        $_ENV['GDRIVE_ALLOWED_HOSTS'] = '';
        $this->assertSame(GDriveAllowlist::HOSTS_DEFAULT, GDriveAllowlist::hostsPermitidos());

        $_ENV['GDRIVE_ALLOWED_HOSTS'] = '   , , ';
        $this->assertSame(GDriveAllowlist::HOSTS_DEFAULT, GDriveAllowlist::hostsPermitidos());
    }

    public function testHostsPermitidosLeeEnvComaSeparadaConEspacios(): void {
        $_ENV['GDRIVE_ALLOWED_HOSTS'] = ' drive.google.com , drive.usercontent.google.com ';

        $this->assertSame(
            ['drive.google.com', 'drive.usercontent.google.com'],
            GDriveAllowlist::hostsPermitidos()
        );
    }

    public function testAdmiteHostsExtraConfiguradosEnEnv(): void {
        // Host futuro de entrega → configurable en .env sin tocar código
        $_ENV['GDRIVE_ALLOWED_HOSTS'] = 'drive.google.com,drive.usercontent.google.com,mistorage.example.com';

        $this->assertTrue(GDriveAllowlist::hostPermitido('mistorage.example.com'));
    }

    public function testAceptaLosHostsDeGoogleDrivePermitidos(): void {
        $this->assertTrue(GDriveAllowlist::hostPermitido('drive.google.com'));
        $this->assertTrue(GDriveAllowlist::hostPermitido('drive.usercontent.google.com'));
    }

    public function testComparacionEsInsensitiveAMayusculas(): void {
        $this->assertTrue(GDriveAllowlist::hostPermitido('DRIVE.GOOGLE.COM'));
        $this->assertTrue(GDriveAllowlist::hostPermitido('Drive.UserContent.Google.Com'));
    }

    public function testRechazaHostsNoPermitidos(): void {
        $rejected = [
            '169.254.169.254',            // metadata cloud
            'metadata.google.internal',   // hostname de metadata
            'evil.com',
            'google.com',                 // dominio padre
            'sub.drive.google.com',       // subdominio
            'drive.google.com.evil.com',  // sufijo engañoso
            'drive.google.com.',          // trailing dot
            '',                            // vacío
        ];

        foreach ($rejected as $host) {
            $this->assertFalse(
                GDriveAllowlist::hostPermitido($host),
                "El host '$host' NO debe estar permitido"
            );
        }
    }
}