<?php
/**
 * config/RateLimiter.php
 * Rate limiting por IP con ventana deslizante, persistido en archivos del
 * directorio temporal del sistema. Mismo patrón que rom_proxy.php, extraído
 * a una clase reutilizable para proteger también el login y otros endpoints.
 */

class RateLimiter {

    /** Namespace por endpoint para no mezclar contadores */
    private const NAMESPACE_DEFAULT = 'default';

    /**
     * Comprueba si la IP está dentro del límite para la ventana actual.
     * Si supera el máximo, devuelve false (debe bloquearse).
     */
    public static function check(string $ip, int $max = 30, int $window = 60, string $namespace = self::NAMESPACE_DEFAULT): bool {
        $dir = self::dir($namespace);
        $file = $dir . '/' . md5($ip) . '.json';

        // Limpiar archivos viejos cada ~100 peticiones (probabilístico)
        if (random_int(1, 100) === 1) {
            foreach (glob($dir . '/*.json') as $f) {
                if (filemtime($f) < time() - $window * 2) @unlink($f);
            }
        }

        $data = file_exists($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!$data || !is_array($data) || $data['window_start'] < time() - $window) {
            file_put_contents($file, json_encode([
                'window_start' => time(),
                'count'        => 1,
            ]), LOCK_EX);
            return true;
        }

        if ((int) $data['count'] >= $max) {
            return false;
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    /**
     * Reinicia el contador de la IP (p. ej. tras un login exitoso).
     */
    public static function reset(string $ip, string $namespace = self::NAMESPACE_DEFAULT): void {
        $file = self::dir($namespace) . '/' . md5($ip) . '.json';
        if (file_exists($file)) @unlink($file);
    }

    /**
     * IP real del cliente.
     *
     * Se confía en REMOTE_ADDR (la IP del peer TCP que Apache/nginx ve).
     * X-Forwarded-For / X-Real-IP SOLO se tienen en cuenta cuando REMOTE_ADDR
     * es una IP de red local (típico de un reverse proxy o XAMPP), porque de
     * lo contrario cualquier cliente podría falsear esas cabeceras y eludir
     * el rate limiting (y además permitiría llenar el disco de archivos).
     */
    public static function clientIp(): string {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        // Si la conexión viene de un proxy de confianza, leer la cabecera real
        if (self::esIpProxyConfiable($remote)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '';
            // Tomar solo la primera IP si hay varias (X-Forwarded-For: ip1, ip2)
            $forwarded = trim((string) explode(',', (string) $forwarded)[0]);

            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }

        // IP del peer TCP validada; si es inválida, fallback compartido
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    /**
     * ¿La IP del peer corresponde a un proxy/reverse-proxy típico?
     * Rango local: 127.0.0.0/8, ::1, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
     */
    private static function esIpProxyConfiable(string $ip): bool {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) return true;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) return true;
            if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) return true;
        }
        return false;
    }

    /**
     * Responde 429 con JSON (AJAX) o con HTML para navegación normal.
     */
    public static function respond429(int $window): void {
        http_response_code(429);
        header('Retry-After: ' . $window);
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.']);
        } else {
            $errorCode  = 429;
            $errorTitle = 'Demasiadas peticiones';
            $errorMsg   = 'Has superado el número máximo de intentos permitidos. Espera unos minutos e inténtalo de nuevo.';
            require_once 'views/layout/header.php';
            require_once 'views/errors/generic.php';
            require_once 'views/layout/footer.php';
        }
        exit;
    }

    /**
     * Directorio persistente del namespace.
     */
    private static function dir(string $namespace): string {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $namespace) ?: self::NAMESPACE_DEFAULT;
        $dir  = sys_get_temp_dir() . '/rv_rate_limit/' . $safe;
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        return $dir;
    }
}
