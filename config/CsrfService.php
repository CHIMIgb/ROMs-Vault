<?php
/**
 * config/CsrfService.php
 * Protección CSRF mediante patrón "Double Submit Cookie":
 *
 *   1. Se genera un token aleatorio por navegador y se guarda en una cookie
 *      httpOnly (rv_csrf). El mismo token se inyecta en cada formulario
 *      (campo oculto csrf_token) y en un <meta name="csrf-token"> para AJAX.
 *   2. En cada petición que cambia estado (POST o mutadores GET) el servidor
 *      compara el token recibido (body/header/query) contra el de la cookie
 *      usando hash_equals(). Si no coinciden → 403.
 *
 * La cookie es httpOnly y SameSite=Strict, así que un atacante externo no
 * puede leerla ni forzarla en el navegador de la víctima.
 */

class CsrfService {

    /** Nombre de la cookie donde se almacena el token */
    private const COOKIE_NAME = 'rv_csrf';

    /** Duración de la cookie (renovable en cada visita) */
    private const COOKIE_TTL = 60 * 60 * 12; // 12 horas

    /** Token generado en este request (para inyectarlo en la respuesta) */
    private static ?string $token = null;

    /**
     * Garantiza que exista un token CSRF para el navegador actual.
     * Si no hay cookie válida, genera una nueva y la establece en la respuesta.
     * Debe llamarse ANTES de cualquier salida de HTML.
     */
    public static function ensureToken(): string {
        if (self::$token !== null) {
            return self::$token;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            self::$token = $cookie;
        } else {
            self::$token = bin2hex(random_bytes(32));
            self::setCookie(self::$token);
        }

        return self::$token;
    }

    /**
     * Devuelve el token actual (generándolo si hace falta).
     */
    public static function getToken(): string {
        return self::ensureToken();
    }

    /**
     * Campo oculto para formularios HTML.
     */
    public static function field(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    /**
     * Meta tag para que el JS lea el token en peticiones AJAX.
     */
    public static function metaTag(): string {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    /**
     * Verifica el token recibido contra el de la cookie.
     * Acepta: campo POST csrf_token, header X-CSRF-Token o query csrf_token.
     */
    public static function verify(): bool {
        $submitted = $_POST['csrf_token'] ?? self::headerToken() ?? ($_GET['csrf_token'] ?? null);
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return false;
        }

        return hash_equals($cookie, $submitted);
    }

    /**
     * Verifica el token para peticiones AJAX (header X-CSRF-Token).
     */
    public static function verifyAjax(): bool {
        $submitted = self::headerToken();
        if ($submitted === null) {
            return false;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!is_string($cookie) || $cookie === '') {
            return false;
        }

        return hash_equals($cookie, $submitted);
    }

    /**
     * Responde 403 con JSON (para AJAX) o HTML (para navegación normal).
     */
    public static function deny(): void {
        http_response_code(403);
        $isAjax = self::isAjax();
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido o expirado. Recarga la página e inténtalo de nuevo.']);
        } else {
            $errorCode  = 403;
            $errorTitle = 'Acceso denegado';
            $errorMsg   = 'Token CSRF inválido o expirado. Recarga la página e inténtalo de nuevo.';
            require_once 'views/layout/header.php';
            require_once 'views/errors/generic.php';
            require_once 'views/layout/footer.php';
        }
        exit;
    }

    /**
     * Detección de petición AJAX.
     */
    public static function isAjax(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Lee el token del header X-CSRF-Token.
     */
    private static function headerToken(): ?string {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($header) && $header !== '' ? $header : null;
    }

    /**
     * Establece la cookie del token (httpOnly + SameSite=Strict).
     */
    private static function setCookie(string $value): void {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
