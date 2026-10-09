<?php
/**
 * config/OriginPolicy.php
 * Política de origen para el proxy de streaming (rom_proxy.php), ítem 3.3 / A10.
 *
 * Anti-hotlink / anti-CSRF de ancho de banda: cuando ALLOWED_ORIGINS está
 * configurado en .env, el proxy exige que las peticiones que declaren un
 * Origin o Referer pertenezcan a un origen permitido.
 *
 * MODO anti-hotlink (Opción B, decidida 2026-10-08):
 *   - ALLOWED_ORIGINS vacío/ausente  → no validar (comportamiento de desarrollo).
 *   - Sin Origin ni Referer          → permitir (descargas con
 *     rel="noopener noreferrer", descargadores CLI con enlace firmado; la
 *     autorización principal sigue siendo la firma HMAC + TTL).
 *   - Origin o Referer presentes     → deben coincidir con un origen permitido;
 *     si ninguno coincide, se rechaza. "Origin: null" siempre se rechaza.
 */

class OriginPolicy {

    /**
     * Lista de orígenes permitidos normalizada a "scheme://host[:puerto-no-default]".
     * Lee $_ENV['ALLOWED_ORIGINS'] (coma-separada, tolera espacios). Vacío → [].
     *
     * @return string[]
     */
    public static function origenesPermitidos(): array {
        $raw = trim((string) ($_ENV['ALLOWED_ORIGINS'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $origenes = [];
        foreach (explode(',', $raw) as $item) {
            $origin = self::extraerOrigen($item);
            if ($origin !== null) {
                $origenes[$origin] = true; // dedupe
            }
        }
        return array_keys($origenes);
    }

    /**
     * Extrae el origin ("scheme://host[:puerto]") de una URL o Referer.
     * Normaliza minúsculas y omite el puerto por defecto (80/443).
     */
    public static function extraerOrigen(string $url): ?string {
        $url = trim($url);
        if ($url === '' || $url === 'null') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($scheme === '' || $host === '') {
            return null;
        }

        $origin = $scheme . '://' . $host;
        $port   = parse_url($url, PHP_URL_PORT);
        if (is_int($port)) {
            $defaultPort = $scheme === 'https' ? 443 : 80;
            if ($port !== $defaultPort) {
                $origin .= ':' . $port;
            }
        }
        return $origin;
    }

    /**
     * ¿La petición supera la política de origen (modo anti-hotlink)?
     *
     * @param string|null $origin  $_SERVER['HTTP_ORIGIN']
     * @param string|null $referer $_SERVER['HTTP_REFERER']
     */
    public static function verificar(?string $origin, ?string $referer): bool {
        $permitidos = self::origenesPermitidos();
        if ($permitidos === []) {
            return true; // ALLOWED_ORIGINS vacío → sin validación
        }

        // "Origin: null" (iframe sandbox / entorno ajeno) siempre se rechaza.
        if ($origin !== null && strtolower(trim($origin)) === 'null') {
            return false;
        }

        // Modo anti-hotlink: sin ningún header → permitir (no se puede juzgar origen).
        if (($origin === null || trim($origin) === '') && ($referer === null || trim($referer) === '')) {
            return true;
        }

        if ($origin !== null && trim($origin) !== '') {
            $candidato = self::extraerOrigen($origin);
            if ($candidato !== null && in_array($candidato, $permitidos, true)) {
                return true;
            }
        }

        if ($referer !== null && trim($referer) !== '') {
            $candidato = self::extraerOrigen($referer);
            if ($candidato !== null && in_array($candidato, $permitidos, true)) {
                return true;
            }
        }

        return false;
    }
}