<?php
// index.php — Router principal
require_once __DIR__ . '/config/AuthMiddleware.php';
require_once __DIR__ . '/config/CsrfService.php';

// ── Cabeceras de seguridad globales (todas las páginas HTML) ────────────────
// CSP equilibrada: permite el CDN de EmulatorJS (scripts, estilos, fuentes,
// imágenes, workers y fetch), blob: (Object URLs del emulador) y scripts/estilos
// inline que ya usa el sitio. Se requiere 'unsafe-eval' porque el runtime de
// Emscripten (cores de EmulatorJS) usa eval()/new Function() internamente
// (cwrap/createNamedFunction) y 'blob:' en script-src porque el core
// descomprimido se inyecta como un Object URL (blob) y el WASM se instancia
// vía fetch desde ese blob (connect-src). Sin ello el emulador no arranca.
// object-src 'none' y frame-ancestors 'self' cierran clickjacking y
// plugin-based XSS.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.emulatorjs.org; "
     . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.emulatorjs.org; "
     . "font-src 'self' https://fonts.gstatic.com data: https://cdn.emulatorjs.org; "
     . "img-src 'self' data: blob: https://cdn.emulatorjs.org; "
     . "media-src 'self' blob: https://cdn.emulatorjs.org; "
     . "connect-src 'self' blob: https://cdn.emulatorjs.org; "
     . "worker-src 'self' blob: https://cdn.emulatorjs.org; "
     . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");

// Cargar usuario actual desde JWT (disponible como $currentUser en las vistas)
$currentUser = AuthMiddleware::getUser();

// Protección CSRF: garantizar token del navegador antes de cualquier salida HTML
CsrfService::ensureToken();

$controller = $_GET['controller'] ?? 'home';
$action     = $_GET['action']     ?? 'index';
$id         = $_GET['id']         ?? null;

// Sanitizar — solo letras y dígitos para controller y action
if (!preg_match('/^[a-zA-Z0-9]+$/', $controller) || !preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
    http_response_code(404);
    require_once 'views/layout/header.php';
    require_once 'views/errors/404.php';
    require_once 'views/layout/footer.php';
    exit;
}

$controllerFile  = 'controllers/' . ucfirst($controller) . 'Controller.php';
$controllerClass = ucfirst($controller) . 'Controller';

if (!file_exists($controllerFile)) {
    http_response_code(404);
    $errorContext = 'Controlador "' . $controller . '" no encontrado.';
    require_once 'views/layout/header.php';
    require_once 'views/errors/404.php';
    require_once 'views/layout/footer.php';
    exit;
}

require_once $controllerFile;

// Protección CSRF global: todo POST debe traer token válido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CsrfService::verify()) {
    CsrfService::deny();
}

$controllerInstance = new $controllerClass();

if (!is_callable([$controllerInstance, $action])) {
    http_response_code(404);
    $errorContext = 'Acción "' . $action . '" no encontrada en el controlador "' . $controller . '".';
    require_once 'views/layout/header.php';
    require_once 'views/errors/404.php';
    require_once 'views/layout/footer.php';
    exit;
}

if ($id !== null) {
    $controllerInstance->$action($id);
} else {
    $controllerInstance->$action();
}
