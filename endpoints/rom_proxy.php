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
 * Modo de acceso a Drive (variable de entorno GDRIVE_MODE, sin degradación
 * automática; ver src/config/GDriveClient.php):
 *   - 'api' (por defecto): lee el binario de la Drive API con una cuenta de
 *     servicio, de modo que las ROMs pueden dejar de ser públicas. Si no hay
 *     credenciales o el access_token falla, responde 503 (error de configuración)
 *     en lugar de caer a la vía pública.
 *   - 'public': usa el enlace público de Drive (desarrollo, CI y rollback).
 *
 * URL de uso:
 *   rom_proxy.php?file_id=GOOGLE_DRIVE_FILE_ID&t=..&sig=..[&dl=1]
 *
 * EmulatorJS lo usará internamente como EJS_gameUrl. `dl=1` añade
 * Content-Disposition: attachment (descarga local), sin cambiar la firma.
 */

// ── Seguridad: evitar ejecución directa sin parámetros ──────────────────────
if (php_sapi_name() === 'cli') {
    die("Este script solo puede ejecutarse desde el servidor web.\n");
}

// ── Cargar entorno (necesario antes del preflight para leer ALLOWED_ORIGINS) ─
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
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

/**
 * Emite un error JSON controlado y termina la ejecución.
 * Centraliza el patrón repetido en este endpoint: nunca se filtra HTML ni
 * detalles internos de Google Drive al cliente.
 */
function emitProxyError(int $httpCode, string $message, string $type): void {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message, 'error_type' => $type]);
    exit;
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
// El enlace público de Drive (modo 'public') vive en GDriveClient::UC_BASE para
// no duplicar el host que ya declara la allowlist (GDriveAllowlist).
define('URL_CACHE_TTL', 600);         // Caché de URLs resueltas de Drive (10 min)

// ── Cargar base de datos ─────────────────────────────────────────────────────

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/RateLimiter.php';
require_once __DIR__ . '/../src/config/UrlSigner.php';
require_once __DIR__ . '/../src/config/GDriveAllowlist.php';
require_once __DIR__ . '/../src/config/GDriveClient.php';
require_once __DIR__ . '/../src/config/OriginPolicy.php';
require_once __DIR__ . '/../src/config/i18n.php';
require_once __DIR__ . '/../src/models/Model.php';
require_once __DIR__ . '/../src/models/Juego.php';

// ── Validar parámetro file_id ────────────────────────────────────────────────
$fileId = $_GET['file_id'] ?? '';

if (empty($fileId) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $fileId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.file_id_invalido')]);
    exit;
}

// ── Validar firma HMAC de la URL (anti-scraping) ─────────────────────────────
$timestamp = (int)($_GET['t']   ?? 0);
$signature = $_GET['sig']       ?? '';
$jwtSecret = $_ENV['JWT_SECRET'] ?? '';

if (empty($signature) || empty($timestamp) || empty($jwtSecret)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.sin_firma'), 'error_type' => 'auth']);
    exit;
}

$expectedSig = hash_hmac('sha256', $fileId . '|' . $timestamp, $jwtSecret);

if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.firma_invalida'), 'error_type' => 'auth']);
    exit;
}

// Verificar que el enlace no haya expirado (SIGNED_URL_TTL, defecto 900 s / 15 min).
// El TTL se resuelve desde UrlSigner::ttl() (única fuente del valor en el proyecto).
define('SIGNED_URL_TTL', UrlSigner::ttl());
if ((time() - $timestamp) > SIGNED_URL_TTL) {
    http_response_code(410);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.expirado'), 'error_type' => 'expired']);
    exit;
}

// ── Validación de origen (anti-hotlink, ítem 3.3 / A10) ─────────────────────
// Con ALLOWED_ORIGINS configurado, las peticiones que declaren Origin o
// Referer deben pertenecer a un origen permitido. Sin ninguno de los dos se
// permiten (descargas <a rel="noopener noreferrer">, descargadores CLI con
// enlace firmado); la autorización principal sigue siendo firma HMAC + TTL.
if (!OriginPolicy::verificar(
    $_SERVER['HTTP_ORIGIN'] ?? null,
    $_SERVER['HTTP_REFERER'] ?? null
)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.origen_denegado'), 'error_type' => 'origin']);
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
        echo json_encode(['error' => t('proxy.demasiadas_peticiones', ['segundos' => $rlWindow]), 'error_type' => 'rate_limit']);
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
    echo json_encode(['error' => t('msg.error_interno_servidor')]);
    error_log('[rom_proxy] Error BD: ' . $e->getMessage());
    exit;
}

if (!$juego) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.no_encontrado')]);
    exit;
}

if (!$juego['activo']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => t('proxy.no_disponible')]);
    exit;
}

// ── Modo de acceso a Drive (GDRIVE_MODE: 'api' por defecto | 'public') ───────
// Sin degradación automática: con GDRIVE_MODE=api y sin credenciales es un
// error de configuración del servidor (503), nunca un fallback silencioso.
$gdriveMode  = GDriveClient::mode();
$useDriveApi = GDriveClient::enabled();

// `dl=1` solo cambia Content-Disposition (descarga); la firma es HMAC(fileId|t),
// así que este parámetro no interviene en la validación.
$download = (($_GET['dl'] ?? '') === '1');

// Cabeceras Bearer para la Drive API (vacío en modo público).
$authHeaders = [];

// GDRIVE_MODE=api pero sin credenciales → error de configuración (no se cae a público).
if ($gdriveMode === GDriveClient::MODE_API && !$useDriveApi) {
    error_log('[rom_proxy] GDRIVE_MODE=api sin credenciales de cuenta de servicio (file_id=' . $fileId . ')');
    emitProxyError(503, t('proxy.modo_sin_credenciales'), 'config');
}

if ($useDriveApi) {
    // ── Modo API: binario vía Drive API (archivos privados) ──────────────────
    if (GDriveClient::authHeaders() === []) {
        error_log('[rom_proxy] Access token no disponible (file_id=' . $fileId . ')');
        emitProxyError(503, t('proxy.modo_sin_credenciales'), 'config');
    }

    $resolvedApi = resolveDriveApiMediaUrl(GDriveClient::mediaUrl($fileId), $fileId);

    if ($resolvedApi['url'] === null) {
        error_log('[rom_proxy] Drive API: ' . $resolvedApi['error'] . ' (file_id=' . $fileId . ')');
        switch ($resolvedApi['error']) {
            case 'auth':
                emitProxyError(502, t('proxy.gdrive_auth'), 'auth');
            case 'private':
                emitProxyError(502, t('proxy.gdrive_bloqueado'), 'private');
            case 'not_found':
                emitProxyError(502, t('proxy.gdrive_no_existe'), 'not_found');
            case 'quota':
                emitProxyError(502, t('proxy.gdrive_cuota'), 'quota');
            case 'config':
                emitProxyError(503, t('proxy.modo_sin_credenciales'), 'config');
            default:
                emitProxyError(502, t('proxy.url_no_resuelta'), 'network');
        }
    }

    $finalUrl  = $resolvedApi['url'];
    $cookieJar = [];

    // La URL final (CDN de Google) no necesita el Bearer; solo se reenvía si la
    // propia Drive API sirvió el binario directo (sin redirect).
    if (!empty($resolvedApi['needs_auth'])) {
        $authHeaders = GDriveClient::authHeaders();
    }
} else {
    // ── Modo público: flujo actual (GDriveClient::UC_BASE + token de confirmación)
    $gdriveUrl = GDriveClient::UC_BASE . urlencode($fileId);

    // Anti-SSRF: la URL inicial también debe estar en la allowlist de hosts
    // (por construcción GDriveClient::UC_BASE apunta a drive.google.com; queda explícito).
    if (!GDriveAllowlist::hostPermitido((string) parse_url($gdriveUrl, PHP_URL_HOST))) {
        error_log('[rom_proxy] Host inicial no permitido para file_id=' . $fileId);
        emitProxyError(502, t('proxy.url_no_resuelta'), 'network');
    }

    // Resolver URL final con cookies (cache → resolución)
    $cached = getCachedUrl($fileId);

    if ($cached) {
        $finalUrl  = $cached['url'];
        $cookieJar = $cached['cookies'];
    } else {
        $resolved = resolveGDriveUrl($gdriveUrl);

        if ($resolved['error'] || !$resolved['url']) {
            error_log('[rom_proxy] Error resolviendo URL para file_id=' . $fileId . ': ' . ($resolved['error'] ?? ''));
            emitProxyError(502, t('proxy.url_no_resuelta'), 'network');
        }

        $finalUrl  = $resolved['url'];
        $cookieJar = $resolved['cookies'];
        cacheUrl($fileId, $finalUrl, $cookieJar);
    }
}

// ── Función: resolver el binario vía Drive API (modo 'api') ─────────────────
/**
 * Pide a la Drive API el binario de un file_id y devuelve la URL final desde
 * la que hacer streaming.
 *
 * La Drive API responde 302 hacia un CDN de Google (drive.usercontent.google.com
 * / *.googleusercontent.com). La redirección se sigue A MANO (FOLLOWLOCATION
 * desactivado) para:
 *   - no reenviar la cabecera Authorization al host final (no filtrar el Bearer);
 *   - validar el host de destino contra GDriveAllowlist (anti-SSRF).
 *
 * Solo se leen las cabeceras: el cuerpo se aborta al primer byte (no se descarga
 * la ROM dos veces). Ante un 401 (token caducado/revocado) se descarta el token
 * en caché y se reintenta UNA vez con uno fresco.
 *
 * @return array{url: ?string, error: ?string, needs_auth: bool}
 *         error: null | 'config' | 'auth' | 'private' | 'not_found' | 'quota' | 'host' | 'network'
 */
function resolveDriveApiMediaUrl(string $mediaUrl, string $fileId): array {
    $intento = 0;

    while (true) {
        $headers = GDriveClient::authHeaders();
        if ($headers === []) {
            return ['url' => null, 'error' => 'config', 'needs_auth' => false];
        }

        $ch = curl_init($mediaUrl);
        $httpCode = 0;
        $location = null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => false,          // 302 a mano: no filtrar el Bearer
            CURLOPT_TIMEOUT        => CONNECT_TIMEOUT,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ROMs-Vault/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$httpCode, &$location) {
                $trimmed = rtrim($headerLine);
                if (preg_match('#^HTTP/[\d.]+ (\d{3})#', $trimmed, $m)) {
                    $httpCode = (int)$m[1];
                } elseif (stripos($trimmed, 'Location:') === 0) {
                    $location = trim(substr($trimmed, 9));
                }
                return strlen($headerLine);
            },
            // Solo interesan las cabeceras: abortar al primer byte de cuerpo.
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                return 0;
            },
        ]);

        curl_exec($ch);
        curl_close($ch);

        // Token caducado/revocado: descartar caché y reintentar una vez.
        if ($httpCode === 401 && $intento === 0) {
            GoogleServiceAccount::clearCache();
            $intento++;
            continue;
        }

        if ($httpCode === 401) {
            return ['url' => null, 'error' => 'auth', 'needs_auth' => false];
        }
        if ($httpCode === 403) {
            return ['url' => null, 'error' => 'private', 'needs_auth' => false];
        }
        if ($httpCode === 404) {
            return ['url' => null, 'error' => 'not_found', 'needs_auth' => false];
        }
        if ($httpCode === 429) {
            return ['url' => null, 'error' => 'quota', 'needs_auth' => false];
        }

        // 200: la Drive API sirve el binario directamente (necesita el Bearer).
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['url' => $mediaUrl, 'error' => null, 'needs_auth' => true];
        }

        // 302/303/307: CDN de Google. Validar host (anti-SSRF) y no reenviar Bearer.
        if ($httpCode >= 300 && $httpCode < 400 && !empty($location)) {
            $host = (string) parse_url($location, PHP_URL_HOST);
            if (!GDriveAllowlist::hostPermitido($host)) {
                error_log('[rom_proxy] Drive API redirige a host no permitido: ' . $host . ' (file_id=' . $fileId . ')');
                return ['url' => null, 'error' => 'host', 'needs_auth' => false];
            }
            return ['url' => $location, 'error' => null, 'needs_auth' => false];
        }

        return ['url' => null, 'error' => 'network', 'needs_auth' => false];
    }
}

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

            // ── Anti-SSRF: solo seguir redirects a hosts de la allowlist ──
            // Cualquier Location a un host no permitido corta la cadena sin
            // conectar. El detalle se deja solo para los logs (anti-información).
            $locationHost = (string) parse_url($location, PHP_URL_HOST);
            if (!GDriveAllowlist::hostPermitido($locationHost)) {
                error_log('[rom_proxy] Redirect a host no permitido en resolveGDriveUrl(): ' . $locationHost);
                return ['url' => null, 'cookies' => [], 'error' => 'host_no_permitido'];
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

// ── Cache de URLs resueltas de Google Drive (solo modo público) ──────────────

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

// ── Anti-SSRF (defensa en profundidad): también se valida el host de la URL
// final (venga del CDN de la Drive API, de la caché o de la resolución) antes
// de abrir el stream. Nunca se conecta a un host que no esté en la allowlist.
$finalHost = (string) parse_url($finalUrl, PHP_URL_HOST);
if (!GDriveAllowlist::hostPermitido($finalHost)) {
    error_log('[rom_proxy] Host final no permitido: ' . $finalHost . ' para file_id=' . $fileId);
    emitProxyError(502, t('proxy.url_no_resuelta'), 'network');
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

// ── Preflight (HEAD) ─────────────────────────────────────────────────────────
// HomeController::checkProxyAccess() comprueba la accesibilidad con un HEAD en
// cada carga de /home/play/{id}. Con HEAD se piden a Drive SOLO las cabeceras
// (CURLOPT_NOBODY) en vez de abrir un GET que habría que cortar al desconectar
// el cliente.
$isHeadRequest = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');

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
    CURLOPT_NOBODY         => $isHeadRequest, // preflight: solo cabeceras
    CURLOPT_FOLLOWLOCATION => true,           // Seguir redirecciones finales
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => READ_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ROMs-Vault/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_BUFFERSIZE     => CHUNK_SIZE,
]);

// Reenviar cookies de sesión de Google (solo modo público)
if (!empty($cookieStr)) {
    curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
}

// Bearer de la Drive API: solo si el binario lo sirve la propia API (sin
// redirect al CDN). En modo público $authHeaders está vacío.
if (!empty($authHeaders)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeaders);
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
    $juego, $cleanTitle, $fileId, $rangeHeader, $download
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
                $errorMsg  = t('proxy.gdrive_no_existe');
            } elseif ($streamHttpCode === 401) {
                $errorType = 'auth';
                $errorMsg  = t('proxy.gdrive_auth');
            } elseif ($streamHttpCode === 403) {
                $errorType = 'private';
                $errorMsg  = t('proxy.gdrive_bloqueado');
            } elseif ($streamHttpCode === 429) {
                $errorType = 'quota';
                $errorMsg  = t('proxy.gdrive_cuota');
            } else {
                $errorType = 'network';
                $errorMsg  = t('proxy.gdrive_error_inesperado', ['codigo' => $streamHttpCode]);
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
        header('Content-Disposition: '
            . ($download ? 'attachment' : 'inline')
            . '; filename="' . $cleanTitle . '"');

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
