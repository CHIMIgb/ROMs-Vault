<?php
/**
 * tests/bootstrap.php
 * Bootstrap de los tests de PHPUnit.
 *
 * Carga el autoload de Composer, define un entorno de prueba determinista
 * (secretos desde tests/.env.test — nunca el .env real) y carga las clases del
 * proyecto que aún no tienen autoload PSR-4 (Fase 3.1 del roadmap).
 */

// 1) Autoload de Composer (contiene firebase/php-jwt usado por JWTService)
require_once __DIR__ . '/../vendor/autoload.php';

// Marca de entorno de test (evita error_log ruidosos en Database, etc.)
define('ROMV_TESTING', true);

// 2) Cargar los secretos locales de test (tests/.env.test, gitignored).
//    createUnsafeImmutable puebla $_ENV/$_SERVER y putenv()/getenv() sin
//    sobrescribir variables ya presentes (CI puede inyectarlas por entorno).
//    Antes de requerir src/config/*: así Dotenv (createImmutable) del .env
//    real no pisa estas claves.
if (is_file(__DIR__ . '/.env.test')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__, '.env.test')->safeLoad();
}

// Helper para leer los secretos TEST_* (.env.test o entorno del proceso).
if (!function_exists('test_env')) {
    function test_env(string $key, ?string $default = null): ?string {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}

// 3) Entorno de prueba aislado del .env real. El secreto JWT no se hardcodea:
//    sale de tests/.env.test (TEST_JWT_SECRET) o, en su defecto, se genera uno
//    efímero solo para esta ejecución (>= 32 bytes: firebase/php-jwt valida la
//    longitud del secreto HS256).
$testJwtSecret = test_env('TEST_JWT_SECRET');
if ($testJwtSecret === null) {
    $testJwtSecret = bin2hex(random_bytes(32));
    $_ENV['TEST_JWT_SECRET'] = $testJwtSecret;
    putenv('TEST_JWT_SECRET=' . $testJwtSecret);
}
$_ENV['JWT_SECRET'] = $testJwtSecret;
putenv('JWT_SECRET=' . $testJwtSecret);
$_ENV['JWT_EXPIRATION'] = '3600';
$_ENV['JWT_REFRESH_THRESHOLD'] = '600';

// Variables DB_* inertes para la suite Unit: Database::tryGetInstance() debe
// fallar rápido (puerto 1, BD inexistente) y devolver null, NUNCA conectar
// al .env real (Neon producción). Dotenv::createImmutable no sobrescribe
// variables ya definidas en $_ENV, así que el .env real queda ignorado.
// (La suite Integration sobrescribe estas claves luego en su propio bootstrap
// con las credenciales de la BD de prueba roms-vault-test.)
$_ENV['DB_HOST']     = '127.0.0.1';
$_ENV['DB_PORT']     = '1';
$_ENV['DB_NAME']     = 'roms-vault-unit-inexistente';
$_ENV['DB_USER']     = 'test';
$_ENV['DB_PASSWORD'] = '';
$_ENV['DB_SSLMODE']  = 'disable';

// 4) Cargar las clases del proyecto (hasta que exista autoload PSR-4)
require_once __DIR__ . '/../src/config/UrlSigner.php';
require_once __DIR__ . '/../src/config/JWTService.php';
require_once __DIR__ . '/../src/config/RateLimiter.php';
require_once __DIR__ . '/../src/config/CsrfService.php';
require_once __DIR__ . '/../src/config/LoggerService.php';
require_once __DIR__ . '/../src/config/TfaService.php';
require_once __DIR__ . '/../src/config/GDriveAllowlist.php';
require_once __DIR__ . '/../src/config/OriginPolicy.php';
require_once __DIR__ . '/../src/config/i18n.php';
require_once __DIR__ . '/../src/config/IntlHelper.php';
require_once __DIR__ . '/../src/config/IdiomasNormalizer.php';
require_once __DIR__ . '/../src/config/TraduccionService.php';
require_once __DIR__ . '/../src/config/TraduccionWorker.php';

// 5) Asegurar un directorio temporal limpio para RateLimiter y LoggerService
$rateDir = sys_get_temp_dir() . '/rv_rate_limit/test';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0700, true);
}
$_ENV['AUTH_LOG_DIR'] = sys_get_temp_dir() . '/rv_logs_test';
