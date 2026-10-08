<?php
/**
 * config/TfaService.php
 * Autenticación de doble factor TOTP (RFC 6238) en PHP puro, sin
 * dependencias Composer (filosofía vanilla del proyecto).
 *
 * Implementa:
 *  - Generación de secretos base32 (RFC 4648) de 20 bytes (clave SHA-1).
 *  - Cálculo y verificación de códigos de 6 dígitos, paso de 30 s, con
 *    ventana de tolerancia (±leeway pasos) para desfases de reloj.
 *  - Comparación en tiempo constante (hash_equals) contra la fuerza bruta.
 *  - Estado "pendiente de 2FA" entre contraseña y código TOTP: cookie
 *    httpOnly firmada con HMAC-SHA256 (clave = JWT_SECRET) y expiración
 *    corta (TFA_PENDING_TTL, default 120 s). Sin tablas adicionales.
 *
 * Valores por defecto compatibles con Google Authenticator / 1Password:
 * algo SHA1, dígitos 6, periodo 30.
 */

class TfaService {

    /** Nombre de la cookie del estado pendiente (no es la sesión). */
    private const PENDING_COOKIE = 'rv_tfa_pending';

    /** Periodo TOTP estándar (segundos). */
    private const PERIOD = 30;

    /**
     * Genera un secreto TOTP nuevo en base32 (20 bytes de entropía).
     */
    public static function generarSecret(): string {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Codifica bytes a base32 (RFC 4648, sin padding).
     */
    public static function base32Encode(string $data): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out      = '';
        $bits     = 0;
        $buffer   = 0;
        $len      = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bits  += 8;
            while ($bits >= 5) {
                $out   .= $alphabet[($buffer >> ($bits - 5)) & 0x1F];
                $bits  -= 5;
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }
        return $out;
    }

    /**
     * Decodifica base32 (acepta padding '=' opcional).
     * Devuelve null si el texto no es base32 válido.
     */
    public static function base32Decode(string $data): ?string {
        $data = strtoupper(trim($data));
        $data = rtrim($data, '=');
        if ($data === '') {
            return '';
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out      = '';
        $bits     = 0;
        $buffer   = 0;
        $len      = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) {
                return null;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits  += 5;
            if ($bits >= 8) {
                $out .= chr(($buffer >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }
        return $out;
    }

    /**
     * Código TOTP de 6 dígitos (por defecto) para un instante (default: ahora).
     * RFC 6238: HMAC-SHA1 sobre el contador de 30 s, truncado dinámico.
     *
     * @param int $digits Longitud del código (6 = Google Authenticator;
     *                    8 = vectores del Apéndice B del RFC 6238)
     */
    public static function codigo(string $secret, ?int $timestamp = null, int $digits = 6): string {
        $key = self::base32Decode($secret);
        if ($key === null || $key === '') {
            return '';
        }
        $digits = ($digits === 8) ? 8 : 6;
        $timestamp = $timestamp ?? time();
        $counter   = (int) floor($timestamp / self::PERIOD);

        // El contador son 8 bytes big-endian (soporta contadores > 32 bits)
        $bin  = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $bin, $key, true);

        $offset  = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value   = ((ord($hash[$offset]) & 0x7F) << 24)
                 | ((ord($hash[$offset + 1]) & 0xFF) << 16)
                 | ((ord($hash[$offset + 2]) & 0xFF) << 8)
                 | (ord($hash[$offset + 3]) & 0xFF);

        $mod = ($digits === 8) ? 100000000 : 1000000;
        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica un código TOTP contra el secreto.
     *
     * @param string $secret Secreto en base32
     * @param string $code   Código de 6 dígitos introducido por el usuario
     * @param int    $leeway Pasos de 30 s de tolerancia (default ±1: ±30 s)
     */
    public static function verificar(string $secret, string $code, int $leeway = 1): bool {
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = time();
        for ($i = -$leeway; $i <= $leeway; $i++) {
            $candidato = self::codigo($secret, $now + ($i * self::PERIOD));
            if ($candidato !== '' && hash_equals($candidato, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * URI otpauth:// para registrar el secreto en una app autenticadora
     * (la mayoría de apps pueden escanear el QR generado a partir de ella).
     */
    public static function provisionUri(string $account, string $secret): string {
        $issuer = (string) ($_ENV['TFA_ISSUER'] ?? 'ROMs Vault');
        $label  = rawurlencode($issuer) . ':' . rawurlencode($account);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Clave HMAC del estado pendiente (JWT_SECRET, ya exigido por la app).
     */
    private static function pendingKey(): string {
        $secret = (string) ($_ENV['JWT_SECRET'] ?? '');
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET no está configurado para firmar el estado 2FA pendiente');
        }
        return $secret;
    }

    /**
     * Emite la cookie del estado pendiente tras validar la contraseña.
     * La cookie es httpOnly, SameSite=Strict y con TTL corto.
     */
    public static function iniciarPending(int $userId): void {
        $ttl      = max(30, (int) ($_ENV['TFA_PENDING_TTL'] ?? 120));
        $exp      = time() + $ttl;
        $payload  = $userId . ':' . $exp;
        $token    = $payload . ':' . hash_hmac('sha256', $payload, self::pendingKey());
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie(self::PENDING_COOKIE, $token, [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Valida la cookie del estado pendiente.
     *
     * @return int|null ID de usuario candidato, o null si no hay estado
     *                  válido (ausente, expirado o firma inválida)
     */
    public static function usuarioPendiente(): ?int {
        $token = $_COOKIE[self::PENDING_COOKIE] ?? '';
        if ($token === '' || substr_count($token, ':') !== 2) {
            return null;
        }
        [$userId, $exp, $sig] = explode(':', $token, 3);
        $userId = (int) $userId;
        $exp    = (int) $exp;
        if ($exp < time()) {
            return null;
        }
        $payload = $userId . ':' . $exp;
        if (!hash_equals(hash_hmac('sha256', $payload, self::pendingKey()), $sig)) {
            return null;
        }
        return $userId;
    }

    /**
     * Limpia el estado pendiente (éxito, error o logout del flujo).
     */
    public static function limpiarPending(): void {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::PENDING_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        unset($_COOKIE[self::PENDING_COOKIE]);
    }
}