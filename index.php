<?php
// index.php — Router principal
require_once __DIR__ . '/config/AuthMiddleware.php';
require_once __DIR__ . '/config/CsrfService.php';

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
