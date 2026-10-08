<?php
/**
 * tests/bootstrap.php
 * Bootstrap de los tests de PHPUnit.
 *
 * Carga el autoload de Composer, define un entorno de prueba determinista
 * (JWT_SECRET fijo — nunca se usa el .env real) y carga las clases del
 * proyecto que aún no tienen autoload PSR-4 (Fase 3.1 del roadmap).
 */

// 1) Autoload de Composer (contiene firebase/php-jwt usado por JWTService)
require_once __DIR__ . '/../vendor/autoload.php';

// Marca de entorno de test (evita error_log ruidosos en Database, etc.)
define('ROMV_TESTING', true);

// 2) Entorno de prueba aislado del .env real
// (>= 32 bytes: firebase/php-jwt valida la longitud del secreto HS256)
$_ENV['JWT_SECRET'] = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';
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

// 3) Cargar las clases del proyecto (hasta que exista autoload PSR-4)
require_once __DIR__ . '/../src/config/UrlSigner.php';
require_once __DIR__ . '/../src/config/JWTService.php';
require_once __DIR__ . '/../src/config/RateLimiter.php';
require_once __DIR__ . '/../src/config/CsrfService.php';
require_once __DIR__ . '/../src/config/LoggerService.php';
require_once __DIR__ . '/../src/config/TfaService.php';

// 4) Asegurar un directorio temporal limpio para RateLimiter y LoggerService
$rateDir = sys_get_temp_dir() . '/rv_rate_limit/test';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0700, true);
}
$_ENV['AUTH_LOG_DIR'] = sys_get_temp_dir() . '/rv_logs_test';
