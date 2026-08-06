<?php
/**
 * router.php — Router del servidor integrado de PHP.
 *
 * Uso:  php -S localhost:8000 router.php
 *
 * - Los archivos reales (raíz y public/) se sirven sin intervención del router,
 *   por lo que ajax_*.php, rom_proxy.php, CSS, JS e imágenes siguen funcionando.
 * - El resto de rutas (/controlador/accion/id) se enrutan a index.php, que
 *   resuelve controller/action/id a partir de REQUEST_URI.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// Servir archivos existentes (raíz o dentro de /public) sin pasar por el router.
$candidates = [__DIR__ . $path, __DIR__ . '/public' . $path];
foreach ($candidates as $file) {
    if ($file !== __DIR__ && $file !== __DIR__ . '/public' && is_file($file)) {
        return false;
    }
}

// Todo lo demás entra al front controller.
require __DIR__ . '/index.php';
