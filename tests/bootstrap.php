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

// 2) Entorno de prueba aislado del .env real
// (>= 32 bytes: firebase/php-jwt valida la longitud del secreto HS256)
$_ENV['JWT_SECRET'] = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';
$_ENV['JWT_EXPIRATION'] = '3600';
$_ENV['JWT_REFRESH_THRESHOLD'] = '600';

// 3) Cargar las clases del proyecto (hasta que exista autoload PSR-4)
require_once __DIR__ . '/../config/UrlSigner.php';
require_once __DIR__ . '/../config/JWTService.php';
require_once __DIR__ . '/../config/RateLimiter.php';
require_once __DIR__ . '/../config/CsrfService.php';

// 4) Asegurar un directorio temporal limpio para RateLimiter
$rateDir = sys_get_temp_dir() . '/rv_rate_limit/test';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0700, true);
}
