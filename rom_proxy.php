<?php
/**
 * rom_proxy.php — Proxy de streaming para ROMs almacenadas en Google Drive
 *
 * Resuelve el problema de CORS que impide que EmulatorJS cargue archivos
 * directamente desde Google Drive. Este proxy:
 *
 *  1. Valida que el file_id exista en la base de datos (seguridad)
 *  2. Resuelve automáticamente las redirecciones de Google Drive
 *  3. Soporta HTTP Range Requests (necesario para que EmulatorJS pueda
 *     hacer seeking dentro del archivo sin descargarlo completo)
 *  4. Emite cabeceras CORS correctas para que el navegador acepte el recurso
 *  5. Hace streaming del contenido en chunks para no saturar la memoria del servidor
 *  6. Gestiona el token de confirmación de descarga que Google exige en archivos grandes
 *
 * URL de uso:
 *   rom_proxy.php?file_id=GOOGLE_DRIVE_FILE_ID
 *
 * EmulatorJS lo usará internamente como EJS_gameUrl.
 */

// ── Seguridad: evitar ejecución directa sin parámetros ──────────────────────
if (php_sapi_name() === 'cli') {
    die("Este script solo puede ejecutarse desde el servidor web.\n");
}

// ── Cargar entorno (necesario antes del preflight para leer ALLOWED_ORIGINS) ─
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// ── Función CORS centralizada ────────────────────────────────────────────────
function emitCorsHeaders(): void {
    $originsEnv = trim($_ENV['ALLOWED_ORIGINS'] ?? '');
    $origin     = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (empty($originsEnv)) {
        // Sin orígenes configurados → permitir todo (desarrollo)
        header('Access-Control-Allow-Origin: *');
    } else {
        $allowed = array_map('trim', explode(',', $originsEnv));
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: null');
        }
    }

    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range, Content-Type');
    header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
}

// ── Responder preflight CORS inmediatamente (sin tocar BD ni Google) ────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    emitCorsHeaders();
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// ── Límites de ejecución ─────────────────────────────────────────────────────
set_time_limit(0);          // Sin timeout — necesario para ROMs grandes (ISOs de 1-2 GB)
ignore_user_abort(false);   // Detener si el cliente cierra la conexión (ahorra recursos)

// ── Configuración ────────────────────────────────────────────────────────────
define('CHUNK_SIZE',   1024 * 1024);  // 1 MB por chunk de streaming (antes 256 KB)
define('MAX_REDIRECTS', 8);           // Máximo de redirecciones a seguir
define('CONNECT_TIMEOUT', 15);        // Segundos para establecer conexión
define('READ_TIMEOUT',    0);         // Sin límite de tiempo de lectura (streaming)
define('GDRIVE_BASE',  'https://drive.google.com/uc?export=download&confirm=t&id=');

// ── Cargar base de datos ─────────────────────────────────────────────────────

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/RateLimiter.php';
require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/Juego.php';

// ── Validar parámetro file_id ────────────────────────────────────────────────
$fileId = $_GET['file_id'] ?? '';

if (empty($fileId) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $fileId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'file_id inválido o ausente']);
    exit;
}

// ── Validar firma HMAC de la URL (anti-scraping) ─────────────────────────────
$timestamp = (int)($_GET['t']   ?? 0);
$signature = $_GET['sig']       ?? '';
$jwtSecret = $_ENV['JWT_SECRET'] ?? '';

if (empty($signature) || empty($timestamp) || empty($jwtSecret)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso denegado: enlace sin firma.', 'error_type' => 'auth']);
    exit;
}

$expectedSig = hash_hmac('sha256', $fileId . '|' . $timestamp, $jwtSecret);

if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acceso denegado: firma inválida.', 'error_type' => 'auth']);
    exit;
}

// Verificar que el enlace no haya expirado (2 horas = 7200 segundos)
define('SIGNED_URL_TTL', 7200);
if ((time() - $timestamp) > SIGNED_URL_TTL) {
    http_response_code(410);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Este enlace ha expirado. Recarga la página.', 'error_type' => 'expired']);
    exit;
}

// ── Rate limiting por IP ─────────────────────────────────────────────────────
// (se delega en config/RateLimiter.php, la misma clase que protege el login)
//
// EXENCIÓN: los Range Requests del emulador (streaming de ROMs grandes) NO se
// limitan. EmulatorJS hace decenas de peticiones de rango por minuto mientras
// el juego lee texturas/audio/geometría; el límite de 30/60 s los estrangulaba
// (429) y los juegos 3D iban lentos o se congelaban. El abuso por esa vía ya
// está bloqueado por la firma HMAC + TTL de 2 h exigida más arriba.
if (empty($_SERVER['HTTP_RANGE'])) {
    $rlMax    = (int)($_ENV['RATE_LIMIT_MAX']    ?? 30);
    $rlWindow = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60);

    if (!RateLimiter::check(RateLimiter::clientIp(), $rlMax, $rlWindow, 'proxy')) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $rlWindow);
        echo json_encode(['error' => 'Demasiadas peticiones. Intenta en ' . $rlWindow . ' segundos.', 'error_type' => 'rate_limit']);
        exit;
    }
}

// ── Verificar que el file_id existe en la BD (seguridad anti-abuso) ──────────
try {
    $juegoModel = new Juego();
    $juego = $juegoModel->findByFileId($fileId);
} catch (Exception $e) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error interno del servidor']);
    error_log('[rom_proxy] Error BD: ' . $e->getMessage());
    exit;
}

if (!$juego) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ROM no encontrada en la base de datos']);
    exit;
}

if (!$juego['activo']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Esta ROM no está disponible']);
    exit;
}

// ── Construir URL inicial de Google Drive ────────────────────────────────────
$gdriveUrl = GDRIVE_BASE . urlencode($fileId);

// ── Función: seguir redirecciones manualmente con cURL ───────────────────────
/**
 * Resuelve todas las redirecciones de Google Drive y devuelve la URL final
 * junto con las cookies de sesión que Google establece para archivos grandes.
 *
 * Google Drive redirige archivos grandes a una URL temporal que incluye
 * un token de confirmación. Hay que capturar las cookies y reenviarlas
 * en la petición final para obtener el contenido real.
 *
 * Archivos >~100 MB (límite del análisis de virus de Google): en lugar de
 * redirigir, Google devuelve una página HTML de confirmación con un token
 * `confirm=TOKEN`. Aquí se captura un fragmento del body, se extrae ese token
 * y se vuelve a resolver la URL con él para llegar al binario real.
 */
function resolveGDriveUrl(string $initialUrl): array {
    $cookieJar   = [];
    $currentUrl  = $initialUrl;
    $maxBodyBytes = 65536; // Solo necesitamos el HTML de confirmación (pocos KB)

    for ($i = 0; $i < MAX_REDIRECTS; $i++) {
        $ch = curl_init($currentUrl);

        $responseHeaders = [];
        $bodySnippet     = '';
        $captureDone     = false;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_HEADERFUNCTION => function($ch, $headerLine) use (&$responseHeaders) {
                $trimmed = rtrim($headerLine);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }
                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$bodySnippet, &$captureDone, $maxBodyBytes) {
                $remaining = $maxBodyBytes - strlen($bodySnippet);
                if ($remaining > 0) {
                    $bodySnippet .= substr($data, 0, $remaining);
                }
                if (strlen($bodySnippet) >= $maxBodyBytes) {
                    $captureDone = true;
                    return 0; // Suficiente para detectar el token; abortar la descarga
                }
                return strlen($data);
            },
            // Range de 64 KB como red de seguridad: nunca se descarga más de eso
            CURLOPT_RANGE          => '0-' . ($maxBodyBytes - 1),
            CURLOPT_FOLLOWLOCATION => false,       // Seguimos redirecciones manualmente
            CURLOPT_TIMEOUT        => CONNECT_TIMEOUT,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ROMs-Vault/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        // Reenviar cookies acumuladas
        if (!empty($cookieJar)) {
            $cookieStr = implode('; ', array_map(
                fn($k, $v) => "$k=$v",
                array_keys($cookieJar),
                $cookieJar
            ));
            curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
        }

        $ok       = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // El único fallo real es no haber capturado ni headers ni body.
        // (Devolver 0 en WRITEFUNCTION aborta con CURLE_WRITE_ERROR: esperado)
        if ($ok === false && !$captureDone && empty($bodySnippet) && empty($responseHeaders)) {
            return ['url' => null, 'cookies' => [], 'error' => 'cURL falló al resolver redirecciones'];
        }

        // Capturar cookies Set-Cookie
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                $cookiePart = trim(substr($header, strlen('Set-Cookie:')));
                $cookiePart = explode(';', $cookiePart)[0]; // Solo nombre=valor
                [$cName, $cVal] = array_pad(explode('=', $cookiePart, 2), 2, '');
                $cookieJar[trim($cName)] = trim($cVal);
            }
        }

        // Si es una redirección, seguirla
        if (in_array($httpCode, [301, 302, 303, 307, 308])) {
            $location = '';
            foreach ($responseHeaders as $header) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));
                    break;
                }
            }

            if (empty($location)) {
                return ['url' => $currentUrl, 'cookies' => $cookieJar, 'error' => null];
            }

            // Resolver URLs relativas
            if (strpos($location, 'http') !== 0) {
                $parsed = parse_url($currentUrl);
                $location = $parsed['scheme'] . '://' . $parsed['host'] . $location;
            }

            $currentUrl = $location;
            continue;
        }

        // Página de confirmación de archivo grande de Google Drive
        // ("Google Drive can't scan this file for viruses"): HTML que contiene
        // un token confirm=TOKEN que hay que añadir a la URL para el binario.
        $isHtml = false;
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') !== false) {
                $isHtml = true;
                break;
            }
        }

        $confirmToken = $isHtml ? extractDriveConfirmToken($bodySnippet) : null;
        if ($confirmToken) {
            $parsed = parse_url($currentUrl);
            parse_str($parsed['query'] ?? '', $query);
            $query['confirm'] = $confirmToken;
            $currentUrl = $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? '/') . '?' . http_build_query($query);
            continue; // Re-resolver con el token de confirmación válido
        }

        // Llegamos a la URL final (binario u otro contenido sin confirmación)
        return ['url' => $currentUrl, 'cookies' => $cookieJar, 'error' => null];
    }

    return ['url' => null, 'cookies' => [], 'error' => 'Demasiadas redirecciones al resolver Google Drive'];
}

/**
 * Extrae el token de confirmación de la página HTML de archivo grande de
 * Google Drive. Google usa varios formatos según la versión:
 *   <input type="hidden" name="confirm" value="TOKEN">
 *   <input type="hidden" value="TOKEN" name="confirm">
 *   href="...?confirm=TOKEN"  (enlace "Download anyway")
 *
 * Devuelve el token, o null si no hay ninguno (el HTML podría ser una página
 * de error real, no la confirmación de descarga).
 */
function extractDriveConfirmToken(string $html): ?string {
    $candidates = [];

    // Formato 1: name="confirm" value="TOKEN"
    if (preg_match('/name=["\']confirm["\']\s+value=["\']([^"\']+)["\']/', $html, $m)) {
        $candidates[] = $m[1];
    }
    // Formato 2: value="TOKEN" name="confirm"
    if (preg_match('/value=["\']([^"\']+)["\']\s+name=["\']confirm["\']/', $html, $m)) {
        $candidates[] = $m[1];
    }
    // Formato 3: confirm=TOKEN en un enlace o parámetro de URL
    if (preg_match('/(?:[?&]|href=["\'])[^"\']*confirm=([A-Za-z0-9_.-]+)/', $html, $m)) {
        $candidates[] = $m[1];
    }

    foreach ($candidates as $token) {
        $token = trim($token);
        // Descartar el 't' inicial (confirm=t) y valores triviales
        if ($token !== '' && $token !== 't' && strlen($token) > 3) {
            return $token;
        }
    }

    return null;
}

/**
 * Convierte el nombre del archivo de Google Drive a una extensión conocida
 * basándose en el nombre del juego y la consola.
 */
function guessContentType(string $titulo, string $consolaNombre): string {
    $console = strtolower($consolaNombre);

    $map = [
        'psp'               => 'application/octet-stream', // .iso / .cso
        'playstation'       => 'application/octet-stream', // .bin / .iso
        'nintendo ds'       => 'application/octet-stream', // .nds
        'game boy advance'  => 'application/octet-stream', // .gba
    ];

    foreach ($map as $key => $mime) {
        if (str_contains($console, $key)) return $mime;
    }

    return 'application/octet-stream';
}

// ── Cache de URLs resueltas de Google Drive ──────────────────────────────────
define('URL_CACHE_TTL', 600); // 10 minutos (conservador)

function getCachedUrl(string $fileId): ?array {
    $cacheFile = sys_get_temp_dir() . '/romproxy_cache/' . md5($fileId) . '.json';
    if (!file_exists($cacheFile)) return null;

    $data = json_decode(file_get_contents($cacheFile), true);
    if (!$data || $data['expires'] < time()) {
        @unlink($cacheFile);
        return null;
    }
    return $data;
}

function cacheUrl(string $fileId, string $url, array $cookies): void {
    $dir = sys_get_temp_dir() . '/romproxy_cache';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);

    $cacheFile = $dir . '/' . md5($fileId) . '.json';
    file_put_contents($cacheFile, json_encode([
        'url'     => $url,
        'cookies' => $cookies,
        'expires' => time() + URL_CACHE_TTL,
    ]), LOCK_EX);
}

// ── Resolver URL final con cookies (cache → resolución) ─────────────────────
$cached = getCachedUrl($fileId);

if ($cached) {
    $finalUrl  = $cached['url'];
    $cookieJar = $cached['cookies'];
} else {
    $resolved = resolveGDriveUrl($gdriveUrl);

    if ($resolved['error'] || !$resolved['url']) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode([
            'error'      => 'No se pudo resolver la URL de Google Drive.',
            'error_type' => 'network',
            'detail'     => $resolved['error'] ?? 'Sin respuesta del servidor de Google Drive.',
        ]);
        error_log('[rom_proxy] Error resolviendo URL para file_id=' . $fileId . ': ' . ($resolved['error'] ?? ''));
        exit;
    }

    $finalUrl  = $resolved['url'];
    $cookieJar = $resolved['cookies'];
    cacheUrl($fileId, $finalUrl, $cookieJar);
}

// ── Procesar Range Request del cliente (para seeking en EmulatorJS) ───────────
$rangeHeader  = '';
$clientRange  = $_SERVER['HTTP_RANGE'] ?? '';

if (!empty($clientRange)) {
    // Validar formato: bytes=inicio-fin  o  bytes=inicio-
    if (preg_match('/^bytes=(\d*)-(\d*)$/', $clientRange, $m)) {
        $rangeHeader = $clientRange; // Lo reenviaremos a Google Drive
    }
}

// ── Streaming del contenido desde Google Drive hacia el cliente ───────────────
$ch = curl_init($finalUrl);

// Construir string de cookies para la petición final
$cookieStr = '';
if (!empty($cookieJar)) {
    $cookieStr = implode('; ', array_map(
        fn($k, $v) => "$k=$v",
        array_keys($cookieJar),
        $cookieJar
    ));
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,          // No acumular en memoria
    CURLOPT_FOLLOWLOCATION => true,           // Seguir redirecciones finales
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => READ_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ROMs-Vault/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_BUFFERSIZE     => CHUNK_SIZE,
]);

// Reenviar cookies de sesión de Google
if (!empty($cookieStr)) {
    curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
}

// Reenviar Range si el cliente lo pidió (para seeking / progressive download)
if (!empty($rangeHeader)) {
    curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $rangeHeader));
}

// ── Preparar nombre de descarga limpio ────────────────────────────────────────
$cleanTitle = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $juego['titulo']);
$cleanTitle = trim(preg_replace('/\s+/', '_', $cleanTitle));

// ── Variables de estado para el HEADERFUNCTION integrado ─────────────────────
$streamHttpCode    = 0;
$streamContentLen  = -1;
$streamContentType = '';
$streamContentRange = '';
$headersSent       = false;
$streamError       = false;

// Capturar cabeceras de Google Drive y emitir las nuestras al terminar los headers
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (
    &$streamHttpCode, &$streamContentLen, &$streamContentType,
    &$streamContentRange, &$headersSent, &$streamError,
    $juego, $cleanTitle, $fileId, $rangeHeader
) {
    $trimmed = rtrim($headerLine);

    // Capturar código HTTP de la línea de estado
    if (preg_match('#^HTTP/[\d.]+ (\d{3})#', $trimmed, $m)) {
        $streamHttpCode = (int)$m[1];
    }

    // Capturar headers relevantes de Google Drive
    if (stripos($trimmed, 'Content-Length:') === 0) {
        $streamContentLen = (int)trim(substr($trimmed, 15));
    }
    if (stripos($trimmed, 'Content-Type:') === 0) {
        $streamContentType = trim(substr($trimmed, 13));
    }
    if (stripos($trimmed, 'Content-Range:') === 0) {
        $streamContentRange = $trimmed;
    }

    // Línea vacía = fin de headers → emitir nuestros headers de respuesta
    if ($trimmed === '' && !$headersSent && $streamHttpCode > 0) {
        $headersSent = true;

        // ── Manejar errores de Google Drive ──────────────────────────────
        if ($streamHttpCode >= 400) {
            if ($streamHttpCode === 404) {
                $errorType = 'not_found';
                $errorMsg  = 'El archivo ya no existe en Google Drive (fue eliminado o el enlace es incorrecto).';
            } elseif ($streamHttpCode === 403) {
                $errorType = 'private';
                $errorMsg  = 'Google Drive ha bloqueado el acceso a este archivo (permisos insuficientes o cuota de descargas superada).';
            } elseif ($streamHttpCode === 429) {
                $errorType = 'quota';
                $errorMsg  = 'Google Drive ha limitado el acceso temporalmente por exceso de descargas. Inténtalo más tarde.';
            } else {
                $errorType = 'network';
                $errorMsg  = "Google Drive devolvió un error inesperado (HTTP $streamHttpCode).";
            }

            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode([
                'error'      => $errorMsg,
                'error_type' => $errorType,
                'http_code'  => $streamHttpCode,
            ]);
            error_log("[rom_proxy] Google Drive HTTP $streamHttpCode ($errorType) para file_id=$fileId");
            $streamError = true;
            return strlen($headerLine);
        }

        // ── Código de respuesta exitoso ──────────────────────────────────
        if ($streamHttpCode === 206) {
            http_response_code(206);
        } else {
            http_response_code(200);
        }

        // ── Cabeceras CORS ──────────────────────────────────────────────
        emitCorsHeaders();

        // ── Cabeceras de seguridad ──────────────────────────────────────
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');

        // ── Cabeceras de contenido ──────────────────────────────────────
        $mimeType = !empty($streamContentType)
            ? explode(';', $streamContentType)[0]
            : 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        // Range Requests (streaming del emulador): permitir caché en el
        // navegador para reutilizar bloques ya leídos (seek hacia atrás).
        // Descargas completas sin Range: no-store (anti-abuso, siempre frescas).
        header($rangeHeader
            ? 'Cache-Control: private, max-age=3600'
            : 'Cache-Control: no-store');
        header('Content-Disposition: inline; filename="' . $cleanTitle . '"');

        // Tamaño del contenido
        if ($streamContentLen > 0) {
            header('Content-Length: ' . (int)$streamContentLen);
        } elseif (!empty($juego['size_bytes']) && $juego['size_bytes'] > 0) {
            header('Content-Length: ' . (int)$juego['size_bytes']);
        }

        // Content-Range si fue una petición parcial
        if (!empty($rangeHeader) && $streamHttpCode === 206 && !empty($streamContentRange)) {
            header($streamContentRange);
        }
    }

    return strlen($headerLine);
});

// Función de escritura: hace streaming chunk a chunk hacia el cliente
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$streamError) {
    // Si hubo error en los headers, abortar el streaming
    if ($streamError) return -1;

    echo $data;
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
    return strlen($data);
});

// ── Activar output buffering sin límite y deshabilitar compresión ─────────────
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', 'Off');

// Limpiar buffers existentes para no enviar basura antes del stream
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ── Ejecutar el stream real ───────────────────────────────────────────────────
$result = curl_exec($ch);

if ($result === false && !$streamError) {
    $curlErr = curl_error($ch);
    error_log('[rom_proxy] cURL streaming error para file_id=' . $fileId . ': ' . $curlErr);
    // No podemos emitir JSON aquí porque los headers ya fueron enviados;
    // el navegador detectará la conexión truncada y play.php lo capturará.
}

curl_close($ch);
exit;
