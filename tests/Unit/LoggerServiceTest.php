<?php
/**
 * tests/Unit/LoggerServiceTest.php
 * Unit tests del logging de seguridad/autenticación (config/LoggerService.php).
 *
 * Cada test usa un directorio propio (AUTH_LOG_DIR sobreescrito) para no
 * contaminar el log compartido de integración (rv_logs_test).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use LoggerService;

class LoggerServiceTest extends TestCase {

    private string $originalLogDir = '';
    private string $testDir;

    protected function setUp(): void {
        parent::setUp();
        $this->originalLogDir = (string) ($_ENV['AUTH_LOG_DIR'] ?? '');
        $this->testDir = sys_get_temp_dir() . '/rv_logs_unit_' . bin2hex(random_bytes(6));
        $_ENV['AUTH_LOG_DIR'] = $this->testDir;
    }

    protected function tearDown(): void {
        if (is_dir($this->testDir)) {
            foreach (glob($this->testDir . '/*.log') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->testDir);
        }
        if ($this->originalLogDir === '') {
            unset($_ENV['AUTH_LOG_DIR']);
        } else {
            $_ENV['AUTH_LOG_DIR'] = $this->originalLogDir;
        }
        parent::tearDown();
    }

    private function logFile(): string {
        return LoggerService::logDir() . '/auth-' . date('Y-m-d') . '.log';
    }

    private function logContent(): string {
        $file = $this->logFile();
        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    public function testWriteCreaArchivoConJSONValido(): void {
        $ok = LoggerService::write('test_event', ['clave' => 'valor'], 'info');

        $this->assertTrue($ok);
        $this->assertFileExists($this->logFile());
        $this->assertDirectoryExists(LoggerService::logDir());

        $lines = file($this->logFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines);
        $data = json_decode((string) $lines[0], true);
        $this->assertIsArray($data);
        $this->assertSame('test_event', $data['event']);
        $this->assertSame('info', $data['level']);
        $this->assertArrayHasKey('ts', $data);
        $this->assertSame('valor', $data['context']['clave']);
    }

    public function testLoginSuccessGeneraEventoConDatosDeUsuario(): void {
        $ok = LoggerService::loginSuccess([
            'user_id'  => 7,
            'username' => 'admin',
            'rol_id'   => 1,
        ], '203.0.113.10');

        $this->assertTrue($ok);
        $log = $this->logContent();
        $this->assertStringContainsString('"event":"login_success"', $log);
        $this->assertStringContainsString('"user_id":7', $log);
        $this->assertStringContainsString('"username":"admin"', $log);
        $this->assertStringContainsString('"ip":"203.0.113.10"', $log);
    }

    public function testLoginFailedGeneraEventoWarningSinPassword(): void {
        LoggerService::loginFailed('admin', '203.0.113.11');

        $log = $this->logContent();
        $this->assertStringContainsString('"event":"login_failed"', $log);
        $this->assertStringContainsString('"level":"warning"', $log);
        $this->assertStringNotContainsString('password', strtolower($log));
    }

    public function testRateLimitedGeneraEventoConUsername(): void {
        LoggerService::rateLimited('admin', '203.0.113.12');

        $log = $this->logContent();
        $this->assertStringContainsString('"event":"rate_limited"', $log);
        $this->assertStringContainsString('"username":"admin"', $log);
    }

    public function testLogoutGeneraEventoConUsuario(): void {
        LoggerService::logout([
            'user_id'  => 7,
            'username' => 'admin',
            'rol_id'   => 1,
        ], '203.0.113.13');

        $log = $this->logContent();
        $this->assertStringContainsString('"event":"logout"', $log);
        $this->assertStringContainsString('"username":"admin"', $log);
    }

    public function testLogoutConUsuarioNullNoLanza(): void {
        // Sin sesión (logout de un anónimo) no debe romper nada
        $ok = LoggerService::logout(null, '203.0.113.14');
        $this->assertTrue($ok);
        $this->assertStringContainsString('"event":"logout"', $this->logContent());
    }

    public function testSanitizaUsernameConSaltosDeLinea(): void {
        // Un atacante podría intentar inyectar líneas falsas en el log
        LoggerService::loginFailed("admin\n2026-10-08T00:00:00+00:00:\"FALSO\"", '203.0.113.15');

        $lines = file($this->logFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $lines, 'El log debe ser de una sola línea por evento');
        $this->assertStringNotContainsString("\n", $lines[0]);
        // Todo debe seguir siendo un único JSON válido
        $data = json_decode((string) $lines[0], true);
        $this->assertIsArray($data);
        $this->assertSame('login_failed', $data['event']);
        $this->assertStringNotContainsString('FALSO"', (string) $lines[0]);
    }

    public function testSanitizaIPInvalidaComoVacia(): void {
        LoggerService::loginFailed('admin', 'no-es-una-ip');
        $this->assertStringContainsString('"ip":""', $this->logContent());
    }

    public function testNoLogueaNuncaElPassword(): void {
        // Aunque un contexto futuro se pase por error con password, se filtra
        // desde AuthController; aquí comprobamos que el evento de login no lo
        // arrastra aunque el array del usuario venga con campos extra.
        LoggerService::loginSuccess([
            'user_id'      => 1,
            'username'     => 'admin',
            'rol_id'       => 1,
            'password_hash' => 'secreto-no-publicable',
        ], '203.0.113.16');

        $this->assertStringNotContainsString('secreto-no-publicable', $this->logContent());
        $this->assertStringNotContainsString('password', strtolower($this->logContent()));
    }

    public function testRotacionPorTamaño(): void {
        $_ENV['LOG_MAX_BYTES'] = '120'; // rotación agresiva para el test

        // Primera línea: crea el archivo del día
        LoggerService::write('evt_a', ['payload' => str_repeat('a', 200)]);
        // Segunda línea: el archivo ya supera el límite → se rota a -1
        LoggerService::write('evt_b', ['payload' => str_repeat('b', 200)]);

        $rotations = glob(LoggerService::logDir() . '/auth-*-*.log') ?: [];
        $this->assertNotEmpty($rotations, 'Debe existir al menos un archivo rotado');

        // El archivo activo vuelve a tener solo la última línea
        $active = file($this->logFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(1, $active);
        $this->assertStringContainsString('evt_b', (string) $active[0]);

        // El rotado conserva la primera
        $rotated = (string) file_get_contents($rotations[0]);
        $this->assertStringContainsString('evt_a', $rotated);
    }

    public function testFailOpenNoLanzaYDevuelveFalse(): void {
        // AUTH_LOG_DIR apunta a una ruta no creable (sobre un archivo)
        $blocker = sys_get_temp_dir() . '/rv_logs_blocker_' . bin2hex(random_bytes(4));
        file_put_contents($blocker, 'x'); // 'directorio' ocupado por un archivo
        $_ENV['AUTH_LOG_DIR'] = $blocker;

        $ok = LoggerService::write('evt_fail', []);
        @unlink($blocker);

        $this->assertFalse($ok);
    }
}