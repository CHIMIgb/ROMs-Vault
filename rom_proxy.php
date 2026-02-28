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

// ── Configuración ────────────────────────────────────────────────────────────
define('CHUNK_SIZE',   1024 * 256);   // 256 KB por chunk de streaming
define('MAX_REDIRECTS', 8);           // Máximo de redirecciones a seguir
define('CONNECT_TIMEOUT', 15);        // Segundos para establecer conexión
define('READ_TIMEOUT',    0);         // Sin límite de tiempo de lectura (streaming)
define('GDRIVE_BASE',  'https://drive.google.com/uc?export=download&confirm=t&id=');

// ── Cargar entorno y base de datos ───────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

require_once __DIR__ . '/config/database.php';
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
 */
function resolveGDriveUrl(string $initialUrl): array {
    $cookieJar = [];
    $currentUrl = $initialUrl;

    for ($i = 0; $i < MAX_REDIRECTS; $i++) {
        $ch = curl_init($currentUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,       // Solo cabeceras, sin body
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['url' => null, 'cookies' => [], 'error' => 'cURL falló al resolver redirecciones'];
        }

        // Extraer cabeceras de la respuesta
        $headers = parseHeaders($response);

        // Capturar cookies Set-Cookie
        foreach ($headers as $header) {
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
            foreach ($headers as $header) {
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

        // Llegamos a la URL final (200 u otro código que no sea redirección)
        break;
    }

    return ['url' => $currentUrl, 'cookies' => $cookieJar, 'error' => null];
}

/**
 * Parsea las líneas de cabeceras HTTP de una respuesta cURL
 */
function parseHeaders(string $rawResponse): array {
    // Las cabeceras van hasta el primer \r\n\r\n
    $headerSection = explode("\r\n\r\n", $rawResponse)[0] ?? '';
    return array_filter(explode("\r\n", $headerSection));
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

// ── Resolver URL final con cookies ───────────────────────────────────────────
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

// Capturar cabeceras de respuesta de Google Drive para reenviarlas al cliente
$responseHeaders = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$responseHeaders) {
    $responseHeaders[] = rtrim($headerLine);
    return strlen($headerLine);
});

// Función de escritura: hace streaming chunk a chunk hacia el cliente
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    echo $data;
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
    return strlen($data);
});

// ── Enviar cabeceras CORS y de control antes de abrir el stream ──────────────
// (No podemos enviarlas después de que empiece el stream)

// Ejecutar cURL en modo "peek" primero para obtener el código HTTP y cabeceras
// sin hacer streaming aún. Lo hacemos con una petición HEAD separada.
$chHead = curl_copy_handle($ch);
curl_setopt($chHead, CURLOPT_NOBODY, true);
curl_setopt($chHead, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chHead, CURLOPT_WRITEFUNCTION, null); // Desactivar writer del stream
curl_exec($chHead);

$httpCode    = curl_getinfo($chHead, CURLINFO_HTTP_CODE);
$contentLen  = curl_getinfo($chHead, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$contentType = curl_getinfo($chHead, CURLINFO_CONTENT_TYPE);
curl_close($chHead);

// ── Determinar código de respuesta HTTP ──────────────────────────────────────
if ($httpCode === 206) {
    // Partial content (Range response)
    http_response_code(206);
} elseif ($httpCode >= 200 && $httpCode < 300) {
    http_response_code(200);
} else {
    // Determinar tipo de error según código HTTP de Google Drive
    if ($httpCode === 404) {
        $errorType   = 'not_found';
        $errorMsg    = 'El archivo ya no existe en Google Drive (fue eliminado o el enlace es incorrecto).';
    } elseif ($httpCode === 403) {
        $errorType   = 'private';
        $errorMsg    = 'Google Drive ha bloqueado el acceso a este archivo (permisos insuficientes o cuota de descargas superada).';
    } elseif ($httpCode === 429) {
        $errorType   = 'quota';
        $errorMsg    = 'Google Drive ha limitado el acceso temporalmente por exceso de descargas. Inténtalo más tarde.';
    } else {
        $errorType   = 'network';
        $errorMsg    = "Google Drive devolvió un error inesperado (HTTP $httpCode).";
    }
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'error'      => $errorMsg,
        'error_type' => $errorType,
        'http_code'  => $httpCode,
    ]);
    error_log("[rom_proxy] Google Drive HTTP $httpCode ($errorType) para file_id=$fileId");
    exit;
}

// ── Cabeceras CORS (permiten que EmulatorJS cargue el archivo) ────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Content-Type');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');

// ── Cabeceras de contenido ────────────────────────────────────────────────────
$mimeType = !empty($contentType) ? explode(';', $contentType)[0] : 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store');  // No cachear ROMs en el proxy (pueden ser grandes)

// Nombre de descarga limpio basado en el título del juego
$cleanTitle = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $juego['titulo']);
$cleanTitle = trim(preg_replace('/\s+/', '_', $cleanTitle));
header('Content-Disposition: inline; filename="' . $cleanTitle . '"');

// Tamaño del contenido (si está disponible)
if ($contentLen > 0) {
    header('Content-Length: ' . (int)$contentLen);
} elseif (!empty($juego['size_bytes']) && $juego['size_bytes'] > 0) {
    header('Content-Length: ' . (int)$juego['size_bytes']);
}

// Content-Range si fue una petición parcial
if (!empty($rangeHeader) && $httpCode === 206) {
    foreach ($responseHeaders as $rh) {
        if (stripos($rh, 'Content-Range:') === 0) {
            header($rh);
            break;
        }
    }
}

// Responder OPTIONS preflight de CORS sin body
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

if ($result === false) {
    $curlErr = curl_error($ch);
    error_log('[rom_proxy] cURL streaming error para file_id=' . $fileId . ': ' . $curlErr);
    // No podemos emitir JSON aquí porque los headers ya fueron enviados;
    // el navegador detectará la conexión truncada y play.php lo capturará.
}

curl_close($ch);
exit;
