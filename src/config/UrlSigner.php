<?php
/**
 * config/UrlSigner.php
 * Firmado HMAC de URLs sensibles (descargas y proxy de ROMs).
 *
 * Centraliza la generación y verificación de enlaces firmados con el mismo
 * formato que usa rom_proxy.php:  hash_hmac('sha256', fileId . '|' . timestamp, JWT_SECRET)
 * para que las URLs generadas por las vistas pasen la validación del proxy.
 *
 * El secreto es JWT_SECRET (ya presente en .env); el .env se carga a través de
 * config/database.php, que se incluye en todos los flujos que usan este helper.
 * La vigencia de los enlaces es SIGNED_URL_TTL (default 900 s), aplicada en el
 * momento de verificar — los enlaces no se almacenan en ningún sitio.
 */

class UrlSigner {

    /**
     * Vigencia por defecto de los enlaces firmados, en segundos.
     *
     * Configurable con SIGNED_URL_TTL en .env; el proxy (rom_proxy.php) usa este
     * mismo método como única fuente del valor. Si el env falta o llega con un
     * valor no positivo se aplica el default de 900 s (15 minutos).
     */
    public static function ttl(): int {
        $value = (int)($_ENV['SIGNED_URL_TTL'] ?? 900);
        return $value > 0 ? $value : 900;
    }

    /**
     * Genera los parámetros de firma para un file_id.
     * @return array{t:int, sig:string}
     */
    public static function sign(string $fileId): array {
        $t   = time();
        $sig = hash_hmac('sha256', $fileId . '|' . $t, self::secret());
        return ['t' => $t, 'sig' => $sig];
    }

    /**
     * Verifica firma + vigencia de un enlace firmado.
     *
     * El TTL se resuelve en cada llamada (self::ttl()) salvo que se pase
     * explícito, de modo que un cambio de SIGNED_URL_TTL se aplica al momento.
     */
    public static function verify(string $fileId, int $timestamp, string $signature, ?int $ttl = null): bool {
        $ttl = $ttl ?? self::ttl();
        if (empty($signature) || $timestamp <= 0) {
            return false;
        }
        if ((time() - $timestamp) > $ttl || $timestamp > time() + 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $fileId . '|' . $timestamp, self::secret());
        return hash_equals($expected, $signature);
    }

    /**
     * URL firmada de descarga (index.php → HomeController::download).
     */
    public static function downloadUrl(string $fileId): string {
        $f = self::sign($fileId);
        return '/home/download?file_id=' . urlencode($fileId)
             . '&t=' . $f['t']
             . '&sig=' . $f['sig'];
    }

    /**
     * URL firmada del proxy de streaming (rom_proxy.php).
     */
    public static function proxyUrl(string $fileId): string {
        $f = self::sign($fileId);
        return '/rom_proxy.php?file_id=' . urlencode($fileId)
             . '&t=' . $f['t']
             . '&sig=' . $f['sig'];
    }

    private static function secret(): string {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET no está configurado en el archivo .env.');
        }
        return $secret;
    }
}
