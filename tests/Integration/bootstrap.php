<?php
/**
 * tests/Integration/bootstrap.php
 * Bootstrap de la suite de integración (cargado por IntegrationTestCase la
 * primera vez; phpunit.xml usa tests/bootstrap.php como bootstrap global).
 *
 * Arranca el servidor HTTP de prueba (php -S con router.php) contra la BD
 * PostgreSQL local de prueba (roms-vault-test) y deja el PDO directo de esa
 * BD en $GLOBALS['TEST_PDO'] para verificar persistencia en los tests.
 *
 * NUNCA toca la BD de producción (Neon) ni el .env real: todas las variables
 * DB_* apuntan a 127.0.0.1:5432/roms-vault-test con DB_SSLMODE=disable.
 */

require_once __DIR__ . '/../bootstrap.php';   // autoload + clases del proyecto
require_once __DIR__ . '/Server.php';         // helper del servidor HTTP

use Tests\Integration\Server;

// Guard: el bootstrap solo se ejecuta una vez por proceso de PHPUnit
if (defined('ROMV_TEST_BOOTSTRAPPED')) {
    return;
}
define('ROMV_TEST_BOOTSTRAPPED', true);

// La contraseña de la BD de prueba se lee SIEMPRE de la variable de entorno
// TEST_DB_PASSWORD (nunca se versiona). El usuario postgres local de la máquina
// de desarrollo es de bajo valor, pero el repo es público: no van credenciales.
$testDbPassword = getenv('TEST_DB_PASSWORD');
if ($testDbPassword === false || $testDbPassword === '') {
    throw new \RuntimeException(
        'Falta la variable de entorno TEST_DB_PASSWORD con la contraseña del ' .
        'PostgreSQL local de prueba. Ej: TEST_DB_PASSWORD=... vendor/bin/phpunit'
    );
}

// ── Entorno DB_* para el PROCESO PHPUnit (los modelos del proyecto se
//    ejecutan aquí, no en el servidor hijo). Dotenv::createImmutable no
//    sobrescribe variables ya definidas, así que el .env real (Neon) queda
//    ignorado en los tests. ──────────────────────────────────────────────
$_ENV['DB_HOST']     = '127.0.0.1';
$_ENV['DB_PORT']     = '5432';
$_ENV['DB_NAME']     = 'roms-vault-test';
$_ENV['DB_USER']     = 'postgres';
$_ENV['DB_PASSWORD'] = $testDbPassword;
$_ENV['DB_SSLMODE']  = 'disable';
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=5432');
putenv('DB_NAME=roms-vault-test');
putenv('DB_USER=postgres');
putenv('DB_PASSWORD=' . $testDbPassword);
putenv('DB_SSLMODE=disable');

// Limpiar rate-limit de login del sistema (evita bloqueos 429 acumulados
// entre ejecuciones de la suite de integración).
$rateLoginDir = sys_get_temp_dir() . '/rv_rate_limit/login';
if (is_dir($rateLoginDir)) {
    foreach (glob($rateLoginDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

// ── Conexión directa a la BD de prueba (para asserts de persistencia) ──────
$GLOBALS['TEST_PDO'] = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=roms-vault-test;sslmode=disable',
    'postgres',
    $testDbPassword,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// ── Arrancar el servidor HTTP de prueba ─────────────────────────────────────
Server::start();

// Detener el servidor al terminar PHPUnit (idempotente)
register_shutdown_function(function () {
    Server::stop();
});
