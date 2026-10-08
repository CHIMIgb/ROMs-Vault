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

// El código de la aplicación vive bajo /src: nunca se sirve por HTTP.
// (La comprobación va ANTES de servir archivos existentes.)
if ($path === '/src' || str_starts_with($path, '/src/')) {
    http_response_code(403);
    return true;
}

// Servir archivos existentes (raíz o dentro de /public) sin pasar por el router.
$candidates = [__DIR__ . $path, __DIR__ . '/public' . $path];
foreach ($candidates as $file) {
    if ($file !== __DIR__ && $file !== __DIR__ . '/public' && is_file($file)) {
        return false;
    }
}

// Entrypoints externos reubicados en /endpoints (rom_proxy + ajax_*). Sus URLs
// públicas (/rom_proxy.php, /ajax_catalog.php, …) se mantienen idénticas.
$entrypoints = [
    'rom_proxy.php',
    'ajax_admin.php',
    'ajax_autocomplete.php',
    'ajax_catalog.php',
    'ajax_categoria.php',
    'ajax_consola.php',
    'ajax_emulador.php',
];
$entry = basename($path);
if (in_array($entry, $entrypoints, true)) {
    require __DIR__ . '/endpoints/' . $entry;
    return true;
}

// Todo lo demás entra al front controller.
require __DIR__ . '/index.php';
