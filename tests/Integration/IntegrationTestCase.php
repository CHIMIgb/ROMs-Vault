<?php
/**
 * tests/Integration/IntegrationTestCase.php
 * Clase base para los tests de integración.
 *
 * - Carga el bootstrap de integración una sola vez (server HTTP + TEST_PDO).
 * - Resetea la BD de prueba antes de cada clase (TRUNCATE + reseed admin)
 *   para que los tests sean deterministas y aislados.
 * - Expone helpers: login(), logout(), pdo() (BD de prueba), csrf().
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase {

    private static bool $booted = false;

    /** @var array|null Credenciales del admin de prueba */
    protected const ADMIN_USER = 'admin';
    protected const ADMIN_PASS = 'admin123';

    public static function setUpBeforeClass(): void {
        self::boot();
        self::resetDatabase();
    }

    /**
     * Carga el bootstrap de integración una vez por proceso.
     */
    private static function boot(): void {
        if (self::$booted) {
            return;
        }
        require_once __DIR__ . '/bootstrap.php';
        self::$booted = true;
    }

    /**
     * PDO directo a la BD de prueba (para verificar persistencia).
     */
    protected function pdo(): \PDO {
        return $GLOBALS['TEST_PDO'];
    }

    /**
     * Limpia la BD de prueba y vuelve a sembrar el admin.
     * Usa TRUNCATE ... CASCADE + los seeds de data/test_seeds.sql.
     */
    protected static function resetDatabase(): void {
        $pdo = $GLOBALS['TEST_PDO'];

        $pdo->exec(
            'TRUNCATE TABLE public.descargas, public.usuarios, public.personas, '
            . 'public.emuladores, public.juegos, public.consolas, public.categorias, '
            . 'public.roles RESTART IDENTITY CASCADE'
        );

        $seedFile = dirname(__DIR__, 2) . '/data/test_seeds.sql';
        $sql = file_get_contents($seedFile);
        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException("No se pudo leer el seed: $seedFile");
        }
        $pdo->exec($sql);
    }

    /**
     * GET con seguimiento de cookies del "navegador" de prueba.
     */
    protected function get(string $path, array $headers = []): array {
        return Server::get($path, $headers);
    }

    /**
     * POST con CSRF automático (cookie rv_csrf ya capturada en el navegador).
     * $data no debe incluir csrf_token: se añade automáticamente.
     */
    protected function post(string $path, array $data, array $headers = []): array {
        $data['csrf_token'] = Server::csrfToken();
        return Server::post($path, $data, $headers);
    }

    /**
     * Login HTTP real como admin de prueba.
     * Devuelve la respuesta del POST (302 en éxito).
     */
    protected function login(): array {
        Server::resetCookies();
        $page = Server::get('/auth/login');
        $this->assertSame(200, $page['status'], 'La página de login debe cargar (200).');
        // OJO: usar $this->post() (añade csrf_token), no Server::post() directo:
        // index.php exige CSRF en TODO POST (línea 81).
        return $this->post('/auth/login', [
            'username' => self::ADMIN_USER,
            'password' => self::ADMIN_PASS,
        ]);
    }

    /**
     * Limpia la sesión del "navegador" de prueba.
     */
    protected function logout(): array {
        $resp = Server::get('/auth/logout');
        Server::resetCookies();
        return $resp;
    }

    /**
     * Al terminar cada clase, limpiar cookies y rate-limit de login para
     * que el estado no contamine la siguiente clase de tests.
     */
    public static function tearDownAfterClass(): void {
        Server::resetCookies();
        $rateDir = sys_get_temp_dir() . '/rv_rate_limit/login';
        foreach (glob($rateDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }
}
