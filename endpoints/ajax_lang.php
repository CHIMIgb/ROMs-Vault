<?php
/**
 * ajax_lang.php
 * Endpoint AJAX — cambia el idioma activo (cookie rv_lang) sin recargar la página.
 * Llamado por public/js/rv-lang.js. Devuelve JSON {ok, lang, html_lang}.
 *
 * Endpoint público (no autenticación). El cambio de idioma es una preferencia
 * de UI sin efecto en datos: no requiere CSRF, pero sí whitelist estricta y un
 * guard same-origin (Sec-Fetch-Site) para evitar cambios dirigidos desde terceros.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/i18n.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Guard same-origin: si el navegador informa el origen de la petición y no es
// del propio sitio, se rechaza (mitiga CSRF de cambio de idioma).
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$lang = $_POST['lang'] ?? '';
if (!is_string($lang) || $lang === '' || !in_array($lang, I18n::LANGUAGES, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid language']);
    exit;
}

// Mismos flags que I18n::setLangCookie() (no sensible; sin HttpOnly; foto de 1 año).
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('rv_lang', $lang, [
    'expires'  => time() + 365 * 24 * 3600,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);
I18n::setLang($lang);

echo json_encode(['ok' => true, 'lang' => $lang, 'html_lang' => $lang]);
exit;
