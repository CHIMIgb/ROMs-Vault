<?php
/**
 * config/RateLimiter.php
 * Rate limiting por IP con ventana deslizante, persistido en archivos del
 * directorio temporal del sistema. Mismo patrón que rom_proxy.php, extraído
*  a una clase reutilizable para proteger también el login y otros endpoints.
 */

require_once __DIR__ . '/i18n.php';

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
     * X-Forwarded-For / X-Real-IP SOLO se leen cuando REMOTE_ADDR coincide con
     * la allowlist explícita TRUSTED_PROXIES de .env (IPs sueltas o redes CIDR
     * v4/v6). Sin variable o vacía = deny-by-default: no se confía en nadie
     * (§12 del plan de seguridad), así un cliente no puede falsear esas
     * cabeceras para eludir el rate limiting ni envenenar la auditoría.
     */
    public static function clientIp(): string {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        // Si la conexión viene de un proxy de confianza, leer la cabecera real
        if (self::proxyDeConfianza($remote)) {
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
     * ¿La IP del peer es un proxy de confianza? Allowlist TRUSTED_PROXIES de
     * .env: lista separada por comas de IPs sueltas y/o redes CIDR (IPv4/IPv6).
     * Vacío/sin variable → false (deny-by-default).
     */
    private static function proxyDeConfianza(string $ip): bool {
        foreach (self::trustedProxies() as $entry) {
            if (self::ipEnRed($ip, $entry)) return true;
        }
        return false;
    }

    /**
     * Entradas de TRUSTED_PROXIES parseadas y cacheadas por valor (si el valor
     * cambia —p. ej. en los tests unitarios— se recalcula).
     */
    private static function trustedProxies(): array {
        static $cacheRaw = null;
        static $cache    = [];

        $raw = trim((string) ($_ENV['TRUSTED_PROXIES'] ?? (getenv('TRUSTED_PROXIES') ?: '')));
        if ($cacheRaw === $raw) return $cache;

        $cacheRaw = $raw;
        $cache    = [];
        if ($raw !== '') {
            foreach (explode(',', $raw) as $entry) {
                $entry = trim($entry);
                if ($entry !== '') $cache[] = $entry;
            }
        }
        return $cache;
    }

    /**
     * ¿La IP cae dentro de la red? Soporta IP suelta o CIDR IPv4/IPv6.
     */
    private static function ipEnRed(string $ip, string $cidr): bool {
        $cidr  = trim($cidr);
        $slash = strpos($cidr, '/');
        if ($slash === false) {
            return $ip === $cidr;
        }

        $net    = substr($cidr, 0, $slash);
        $prefix = substr($cidr, $slash + 1);
        if (!ctype_digit($prefix)) return false;
        $prefix = (int) $prefix;

        $ipBin  = @inet_pton($ip);
        $netBin = @inet_pton($net);
        if ($ipBin === false || $netBin === false) return false;

        $bits = strlen($ipBin) * 8;
        if ($prefix > $bits) return false;

        $mask = str_repeat("\xff", intdiv($prefix, 8));
        if ($prefix % 8 !== 0) {
            $mask .= chr(0xff & (0xff << (8 - ($prefix % 8))));
        }
        $mask = str_pad($mask, strlen($ipBin), "\0");

        // Comparar solo la longitud del prefijo (ignorar el padding)
        return (substr($ipBin, 0, strlen($mask)) & $mask)
            === (substr($netBin, 0, strlen($mask)) & $mask);
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
            echo json_encode(['ok' => false, 'error' => t('rate.demasiados_intentos')]);
        } else {
            $errorCode  = 429;
            $errorTitle = t('rate.demasiadas_peticiones');
            $errorMsg   = t('rate.limite_intentos');
            require_once __DIR__ . '/../views/layout/header.php';
            require_once __DIR__ . '/../views/errors/generic.php';
            require_once __DIR__ . '/../views/layout/footer.php';
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
