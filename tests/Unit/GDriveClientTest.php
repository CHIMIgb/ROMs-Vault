<?php
/**
 * tests/Unit/GDriveClientTest.php
 * Unit tests del cliente de la Google Drive API (config/GDriveClient.php).
 *
 * Todo es offline: se comprueba la selección de modo (GDRIVE_MODE) y que
 * enabled()/authHeaders() no degradan a público por su cuenta. Nunca se llama
 * a la red (no se pide un access_token real).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use GDriveClient;
use GoogleServiceAccount;

class GDriveClientTest extends TestCase {

    /** @var array<string, mixed> */
    private array $envOriginal = [];

    private const CLAVES = ['GDRIVE_MODE', 'GOOGLE_SERVICE_ACCOUNT_FILE', 'GOOGLE_SERVICE_ACCOUNT_JSON_B64'];

    protected function setUp(): void {
        parent::setUp();

        foreach (self::CLAVES as $key) {
            $this->envOriginal[$key] = $_ENV[$key] ?? '__SIN_SET__';
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void {
        foreach ($this->envOriginal as $key => $value) {
            if ($value === '__SIN_SET__') {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        if ($this->archivoCreds !== null && is_file($this->archivoCreds)) {
            @unlink($this->archivoCreds);
        }
        parent::tearDown();
    }

    /** Ruta del JSON de credenciales temporal, para limpiarlo en tearDown. */
    private ?string $archivoCreds = null;

    /** Credenciales mínimas (no se firma nada en estos tests). */
    private function configurarCredenciales(): void {
        $this->archivoCreds = sys_get_temp_dir() . '/rv_gdrive_test_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($this->archivoCreds, json_encode([
            'client_email' => 'roms-vault-proxy@roms-vault.iam.gserviceaccount.com',
            'private_key'  => 'PEM',
        ]));
        $_ENV['GOOGLE_SERVICE_ACCOUNT_FILE'] = $this->archivoCreds;
    }

    public function testModoPorDefectoEsApi(): void {
        $this->assertSame(GDriveClient::MODE_API, GDriveClient::mode());
    }

    public function testModoPublicSeReconoce(): void {
        $_ENV['GDRIVE_MODE'] = 'public';
        $this->assertSame(GDriveClient::MODE_PUBLIC, GDriveClient::mode());
    }

    public function testModoPublicIgnoraMayusculasYEspacios(): void {
        $_ENV['GDRIVE_MODE'] = '  PUBLIC  ';
        $this->assertSame(GDriveClient::MODE_PUBLIC, GDriveClient::mode());
    }

    public function testModoDesconocidoCaeAApi(): void {
        foreach (['api', 'foo', '0', 'automatico'] as $valor) {
            $_ENV['GDRIVE_MODE'] = $valor;
            $this->assertSame(GDriveClient::MODE_API, GDriveClient::mode(), "valor: $valor");
        }
    }

    public function testEnabledEsFalsoEnModoPublicAunqueHayaCredenciales(): void {
        $_ENV['GDRIVE_MODE'] = 'public';
        $this->configurarCredenciales();

        $this->assertFalse(GDriveClient::enabled());
    }

    public function testEnabledEsFalsoEnModoApiSinCredenciales(): void {
        $this->assertSame(GDriveClient::MODE_API, GDriveClient::mode());
        $this->assertFalse(GDriveClient::enabled());
    }

    public function testEnabledEsVerdaderoEnModoApiConCredenciales(): void {
        $this->configurarCredenciales();

        $this->assertTrue(GDriveClient::enabled());
    }

    public function testAuthHeadersVacioSinCredenciales(): void {
        $this->assertSame([], GDriveClient::authHeaders());
    }

    public function testMediaUrlUsaAltMediaYEscapaElFileId(): void {
        $url = GDriveClient::mediaUrl('abc_123');

        $this->assertStringStartsWith('https://www.googleapis.com/drive/v3/files/', $url);
        $this->assertStringContainsString('abc_123', $url);
        $this->assertStringContainsString('alt=media', $url);
        $this->assertStringContainsString('supportsAllDrives=true', $url);

        // El file_id se escapa: '/' y espacios no pueden inyectar ruta ni query.
        $escapado = GDriveClient::mediaUrl('a/b c');
        $this->assertStringContainsString(rawurlencode('a/b c'), $escapado);
        $this->assertStringNotContainsString('a/b', $escapado);
    }

    /** Restaura el limpia-tokens para no interferir con otros tests. */
    public static function tearDownAfterClass(): void {
        GoogleServiceAccount::clearCache();
    }
}
