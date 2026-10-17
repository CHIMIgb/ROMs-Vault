<?php
// index.php — Router principal
require_once __DIR__ . '/src/config/AuthMiddleware.php';
require_once __DIR__ . '/src/config/CsrfService.php';
require_once __DIR__ . '/src/config/i18n.php';
require_once __DIR__ . '/src/config/IntlHelper.php';
require_once __DIR__ . '/src/config/EmulatorJS.php';
require_once __DIR__ . '/src/config/GoogleFonts.php';
require_once __DIR__ . '/src/config/SecurityHeaders.php';

// ── Selector de idioma (?lang=es|en) — ítem 6.2 ──────────────────────────────
// Persiste la preferencia en la cookie rv_lang y redirige a la MISMA URL sin el
// parámetro (no se duplica ninguna URL; SEO y enlaces intactos). Antes de
// I18n::init() para que la cookie ya exista al detectar el idioma.
if (isset($_GET['lang']) && in_array($_GET['lang'], I18n::LANGUAGES, true)) {
    I18n::setLangCookie($_GET['lang']);
    $parsed = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    parse_str($parsed['query'] ?? '', $query);
    unset($query['lang']);
    $qs = http_build_query($query);
    $target = ($parsed['path'] ?? '/') . ($qs !== '' ? '?' . $qs : '');
    header('Location: ' . $target);
    exit;
}

I18n::init(); // t() disponible en todas las páginas HTML

// ── Cabeceras de seguridad globales (todas las páginas HTML) ────────────────
// La política (CSP) y su porqué viven en src/config/SecurityHeaders.php, junto
// a los hosts externos declarados una sola vez (EmulatorJS, Google Fonts, R2).
SecurityHeaders::emitir();

// Cargar usuario actual desde JWT (disponible como $currentUser en las vistas)
$currentUser = AuthMiddleware::getUser();

// Protección CSRF: garantizar token del navegador antes de cualquier salida HTML
CsrfService::ensureToken();

// ── Resolución de rutas ────────────────────────────────────────────────────
// URLs limpias: /controlador/accion/id
// Si no viene ?controller= en el query string (enlaces nuevos, php -S con
// router.php, o Apache vía .htaccess), se parsean los segmentos de REQUEST_URI.
// Las URLs clásicas index.php?controller=X&action=Y&id=N siguen funcionando.
if (!isset($_GET['controller'])) {
    $uriPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uriPath  = trim($uriPath, '/');
    $segments = $uriPath === '' ? [] : explode('/', $uriPath);

    // Rutas "pretty" con slug (ítem 7.2): /juego/{slug}, /jugar/{slug},
    // /consolas/{slug}. Se resuelven ANTES de la convención controller/action.
    // Nota: la página pública de consola usa /consolas (plural) porque
    // /consola/* es el CRUD de admin (ConsolaController) y debe seguir igual.
    // El slug viaja en $_GET['id'] para reutilizar el dispatch genérico (se
    // recibe como primer argumento del método). La validación del slug la hace
    // el modelo con consultas preparadas.
    $prettyRoutes = [
        'juego'    => 'showBySlug',
        'jugar'    => 'playBySlug',
        'consolas' => 'consolaBySlug',
    ];
    if (isset($prettyRoutes[$segments[0] ?? '']) && !empty($segments[1])) {
        $_GET['controller'] = 'home';
        $_GET['action']     = $prettyRoutes[$segments[0]];
        $_GET['id']         = $segments[1];
    } else {
        $_GET['controller'] = $segments[0] ?? 'home';
        $_GET['action']     = $segments[1] ?? 'index';
        if (isset($segments[2]) && $segments[2] !== '') {
            $_GET['id'] = $segments[2];
        }
    }
}

$controller = $_GET['controller'] ?? 'home';
$action     = !empty($_GET['action']) ? $_GET['action'] : 'index';
$id         = $_GET['id']         ?? null;

// Sanitizar — solo letras y dígitos para controller y action
if (!preg_match('/^[a-zA-Z0-9]+$/', $controller) || !preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
    http_response_code(404);
    require_once __DIR__ . '/src/views/layout/header.php';
    require_once __DIR__ . '/src/views/errors/404.php';
    require_once __DIR__ . '/src/views/layout/footer.php';
    exit;
}

// No almacenar las páginas de admin en cachés (datos de gestión; ítem 9.4). Se
// emite antes de cargar el controlador para cubrir también redirects 302/403
// del panel; el resto de páginas públicas no lo llevan.
if ($controller === 'admin') {
    header('Cache-Control: no-store');
}

$controllerFile  = 'src/controllers/' . ucfirst($controller) . 'Controller.php';
$controllerClass = ucfirst($controller) . 'Controller';

if (!file_exists($controllerFile)) {
    http_response_code(404);
    $errorContext = t('msg.controlador_no_encontrado', ['controller' => $controller]);
    require_once __DIR__ . '/src/views/layout/header.php';
    require_once __DIR__ . '/src/views/errors/404.php';
    require_once __DIR__ . '/src/views/layout/footer.php';
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
    $errorContext = t('msg.accion_no_encontrada', ['action' => $action, 'controller' => $controller]);
    require_once __DIR__ . '/src/views/layout/header.php';
    require_once __DIR__ . '/src/views/errors/404.php';
    require_once __DIR__ . '/src/views/layout/footer.php';
    exit;
}

if ($id !== null) {
    $controllerInstance->$action($id);
} else {
    $controllerInstance->$action();
}
