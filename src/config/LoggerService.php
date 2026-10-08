<?php
/**
 * config/LoggerService.php
 * Logging estructurado de seguridad/autenticación en archivo (JSON Lines).
 *
 * Registra eventos de auditoría (login fallido, login exitoso, rate limit,
 * logout) en un archivo rotado por día y por tamaño, legible por herramientas
 * externas de monitoreo. Sigue el patrón estático de RateLimiter: sin
 * framework, configurable vía variables de entorno.
 *
 * Configuración (`.env`):
 *   AUTH_LOG_DIR  — ruta absoluta del directorio de logs
 *                   (vacío/ausente = sys_get_temp_dir()/rv_logs).
 *   LOG_MAX_BYTES — tamaño máximo por archivo antes de rotar (default 10 MB).
 *
 * Garantías de seguridad:
 *   - Fail-open: si no se puede escribir, devuelve false pero NUNCA rompe el
 *     flujo (login, logout, etc.) ni lanza excepciones al llamante.
 *   - Nunca se registran contraseñas ni datos sensibles.
 *   - Permisos: directorio 0700, archivo 0600.
 *   - Los valores de contexto (usuario, IP) se sanitizan.
 */

class LoggerService {

    /** Tamaño máximo por archivo antes de rotar (10 MB). */
    private const DEFAULT_MAX_BYTES = 10485760;

    /**
     * Registra un login exitoso.
     *
     * @param array  $user Datos del usuario ('user_id', 'username', 'rol_id')
     * @param string $ip   IP del cliente (RateLimiter::clientIp() sugerida)
     */
    public static function loginSuccess(array $user, string $ip = ''): bool {
        return self::write('login_success', [
            'user_id'  => self::userId($user),
            'username' => self::sanitizeUsername($user['username'] ?? ''),
            'rol_id'   => isset($user['rol_id']) ? (int) $user['rol_id'] : null,
            'ip'       => self::sanitizeIp($ip),
        ]);
    }

    /**
     * Registra un intento de login fallido (credenciales inválidas).
     */
    public static function loginFailed(string $username, string $ip = ''): bool {
        return self::write('login_failed', [
            'username' => self::sanitizeUsername($username),
            'ip'       => self::sanitizeIp($ip),
        ], 'warning');
    }

    /**
     * Registra un bloqueo por rate limit (posible fuerza bruta).
     */
    public static function rateLimited(string $username, string $ip = ''): bool {
        return self::write('rate_limited', [
            'username' => self::sanitizeUsername($username),
            'ip'       => self::sanitizeIp($ip),
        ], 'warning');
    }

    /**
     * Registra un cierre de sesión.
     *
     * @param array|null $user Usuario antes de borrar la cookie (o null)
     */
    public static function logout(?array $user, string $ip = ''): bool {
        return self::write('logout', [
            'user_id'  => self::userId($user ?? []),
            'username' => self::sanitizeUsername($user['username'] ?? ''),
            'rol_id'   => isset($user['rol_id']) ? (int) $user['rol_id'] : null,
            'ip'       => self::sanitizeIp($ip),
        ]);
    }

    /**
     * Escribe una línea JSON en el archivo de logs del día.
     *
     * Formato por línea:
     *   {"ts":"2026-10-08T12:00:00+00:00","level":"info","event":"...","context":{...}}
     *
     * @param string $event   Nombre del evento (kebab_case)
     * @param array  $context Datos estructurados del evento (ya sanitizados)
     * @param string $level   Nivel de severidad: info, warning, error, critical
     * @return bool true si se escribió; false si no se pudo (fail-open)
     */
    public static function write(string $event, array $context = [], string $level = 'info'): bool {
        $file = self::currentFile();
        if ($file === null) {
            return false;
        }

        $line = json_encode([
            'ts'      => date(DATE_ATOM),
            'level'   => $level,
            'event'   => $event,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($line === false) {
            return false;
        }

        try {
            self::rotateIfNeeded($file);

            $flags = is_file($file) ? (LOCK_EX | FILE_APPEND) : LOCK_EX;
            $written = @file_put_contents($file, $line . "\n", $flags);
            if ($written === false) {
                return false;
            }

            @chmod($file, 0600);
            return true;
        } catch (\Throwable $e) {
            // Fail-open: el logging jamás debe interrumpir la aplicación.
            return false;
        }
    }

    /**
     * Ruta absoluta del directorio de logs.
     */
    public static function logDir(): string {
        $dir = trim((string) ($_ENV['AUTH_LOG_DIR'] ?? ''));
        if ($dir === '') {
            $dir = sys_get_temp_dir() . '/rv_logs';
        }
        return $dir;
    }

    /**
     * Ruta del archivo activo del día: {logDir}/auth-YYYY-MM-DD.log
     * Crea el directorio si hace falta. Devuelve null si no pudo crearlo.
     */
    public static function currentFile(): ?string {
        try {
            $dir = self::logDir();
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                return null;
            }
            return rtrim($dir, '/\\') . '/auth-' . date('Y-m-d') . '.log';
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Rota el archivo actual si superó LOG_MAX_BYTES.
     * El archivo actual se renombra a auth-YYYY-MM-DD-1.log, -2.log, etc.
     */
    private static function rotateIfNeeded(string $file): void {
        if (!is_file($file)) {
            return;
        }

        $max = (int) ($_ENV['LOG_MAX_BYTES'] ?? self::DEFAULT_MAX_BYTES);
        if ($max <= 0) {
            return;
        }
        if (filesize($file) < $max) {
            return;
        }

        $base = substr($file, 0, -4); // quitar .log
        $n = 1;
        while (is_file($base . '-' . $n . '.log')) {
            $n++;
        }
        @rename($file, $base . '-' . $n . '.log');
    }

    /** ID de usuario seguro (int) o null. Acepta 'user_id' y 'id' (legacy). */
    private static function userId(array $user): ?int {
        $id = $user['user_id'] ?? $user['id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /**
     * Normaliza un usuario para el log: recorta, elimina caracteres de
     * control (evita inyección de líneas en el archivo) y limita longitud.
     */
    private static function sanitizeUsername(string $username): string {
        $username = trim($username);
        // Quitar caracteres de control (incluye \n y \r) de forma UTF-8 segura.
        $username = preg_replace('/[\x00-\x1F\x7F]/u', '', $username) ?? '';
        // Truncado UTF-8 seguro a 64 caracteres (evita JSON inválido).
        if (preg_match('/^.{0,64}/us', $username, $m)) {
            return (string) $m[0];
        }
        return $username;
    }

    /** IP truncada/límites básicos; vacío si no es IP válida. */
    private static function sanitizeIp(string $ip): string {
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}