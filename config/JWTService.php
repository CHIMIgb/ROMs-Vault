<?php
/**
 * config/JWTService.php
 * Servicio central para generación, validación y gestión de tokens JWT.
 * Utiliza cookies httpOnly para almacenar el token de forma segura.
 * Incluye auto-refresh: renueva el token automáticamente cuando está
 * próximo a expirar para mantener la sesión activa.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno (necesario para JWT_SECRET, JWT_EXPIRATION, etc.)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class JWTService {

    /** Nombre de la cookie donde se almacena el JWT */
    private const COOKIE_NAME = 'rv_token';

    /**
     * Obtiene la clave secreta desde las variables de entorno.
     */
    private static function getSecret(): string {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET no está configurado en el archivo .env');
        }
        return $secret;
    }

    /**
     * Obtiene el tiempo de expiración en segundos (por defecto 1 hora).
     */
    private static function getExpiration(): int {
        return (int)($_ENV['JWT_EXPIRATION'] ?? 3600);
    }

    /**
     * Obtiene el umbral de refresh en segundos (por defecto 10 minutos).
     * Cuando faltan menos de estos segundos para que expire el token,
     * se genera uno nuevo automáticamente.
     */
    private static function getRefreshThreshold(): int {
        return (int)($_ENV['JWT_REFRESH_THRESHOLD'] ?? 600);
    }

    /**
     * Genera un token JWT con los datos del usuario.
     *
     * @param array $user Datos del usuario (debe contener 'id', 'username', 'rol_id')
     * @return string Token JWT codificado
     */
    public static function generate(array $user): string {
        $now = time();
        $payload = [
            'sub'      => $user['id'],
            'username' => $user['username'],
            'rol_id'   => $user['rol_id'],
            'iat'      => $now,
            'exp'      => $now + self::getExpiration(),
        ];

        return JWT::encode($payload, self::getSecret(), 'HS256');
    }

    /**
     * Decodifica y valida un token JWT.
     *
     * @param string $token Token JWT a decodificar
     * @return array|null Payload del token o null si es inválido/expirado
     */
    public static function decode(string $token): ?array {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecret(), 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            // Token expirado
            return null;
        } catch (\Exception $e) {
            // Token inválido, manipulado, etc.
            return null;
        }
    }

    /**
     * Lee y valida el token JWT desde la cookie del request actual.
     *
     * @return array|null Payload del usuario o null si no hay token válido
     */
    public static function validateFromRequest(): ?array {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (empty($token)) {
            return null;
        }
        return self::decode($token);
    }

    /**
     * Verifica si el token actual está próximo a expirar y, de ser así,
     * genera un nuevo token con la expiración renovada.
     *
     * Se considera "próximo a expirar" cuando faltan menos de
     * JWT_REFRESH_THRESHOLD segundos (por defecto 10 minutos).
     *
     * @param array $payload Payload decodificado del token actual
     * @return void
     */
    public static function refreshIfNeeded(array $payload): void {
        $exp = $payload['exp'] ?? 0;
        $remaining = $exp - time();

        // Si faltan menos del umbral de refresh, renovar el token
        if ($remaining > 0 && $remaining <= self::getRefreshThreshold()) {
            $user = [
                'id'       => $payload['sub'],
                'username' => $payload['username'],
                'rol_id'   => $payload['rol_id'],
            ];

            $newToken = self::generate($user);
            self::setTokenCookie($newToken);
        }
    }

    /**
     * Establece la cookie httpOnly con el token JWT.
     *
     * @param string $token Token JWT generado
     */
    public static function setTokenCookie(string $token): void {
        $expiration = time() + self::getExpiration();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => $expiration,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Elimina la cookie del token JWT (logout).
     */
    public static function clearTokenCookie(): void {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly'  => true,
            'samesite' => 'Strict',
        ]);

        // Limpiar también de $_COOKIE para el request actual
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Helper: obtiene los datos del usuario actual desde el JWT.
     * Si el token está próximo a expirar, lo renueva automáticamente.
     * Retorna un array asociativo compatible con el formato anterior de $_SESSION.
     *
     * @return array|null ['user_id' => ..., 'username' => ..., 'rol_id' => ...] o null
     */
    public static function getCurrentUser(): ?array {
        $payload = self::validateFromRequest();
        if ($payload === null) {
            return null;
        }

        // Auto-refresh: renovar token si está próximo a expirar
        self::refreshIfNeeded($payload);

        return [
            'user_id'  => $payload['sub'],
            'username' => $payload['username'],
            'rol_id'   => $payload['rol_id'],
        ];
    }
}
