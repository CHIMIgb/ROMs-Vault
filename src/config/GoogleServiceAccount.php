<?php

/**
 * config/GoogleServiceAccount.php
 * Autenticación con una cuenta de servicio de Google (OAuth 2.0 JWT-bearer).
 *
 * Permite al proxy de ROMs (endpoints/rom_proxy.php) leer archivos PRIVADOS de
 * Google Drive a través de la Drive API sin que el visitante se autentique: la
 * cuenta de servicio es una identidad *server-side*, invisible para el usuario
 * final. Se firma una aserción JWT (RS256) con la clave privada de la cuenta y
 * se canjea en el endpoint OAuth de Google por un access_token (scope mínimo
 * drive.readonly), que se cachea en disco hasta poco antes de expirar.
 *
 * Credenciales (en este orden):
 *   1. GOOGLE_SERVICE_ACCOUNT_FILE     ruta al JSON de la cuenta (dev/servidor).
 *   2. GOOGLE_SERVICE_ACCOUNT_JSON_B64 el mismo JSON en base64 (prod sin disco).
 *
 * El modo lo decide EXPLÍCITAMENTE GDRIVE_MODE (api|public); sin degradación
 * automática: con GDRIVE_MODE=api y sin credenciales válidas, isConfigured()
 * devuelve false y el proxy responde 503 en lugar de caer a la vía pública.
 * Ver el plan en docs/planificado/2026-10-14-drive-privado-service-account.md.
 *
 * La clave privada NUNCA sale del servidor ni se escribe en los logs.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

class GoogleServiceAccount {
    /** Scope mínimo: solo lectura de Drive. */
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';

    /** Endpoint OAuth2 por defecto (puede venir en el JSON como token_uri). */
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** Tipo de concesión para el canje de la aserción. */
    private const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    /** Vigencia de la aserción JWT, en segundos. */
    private const ASSERTION_TTL = 3600;

    /** Refresco anticipado del access_token (margen antes de expirar). */
    private const TOKEN_MARGIN = 60;

    /** Timeout de la llamada a oauth2 (segundos). */
    private const TOKEN_TIMEOUT = 15;

    /** ¿Hay credenciales de cuenta de servicio válidas configuradas? */
    public static function isConfigured(): bool {
        return self::credentials() !== null;
    }

    /**
     * Carga y valida las credenciales de la cuenta de servicio.
     *
     * Prioridad: GOOGLE_SERVICE_ACCOUNT_FILE (ruta) → GOOGLE_SERVICE_ACCOUNT_JSON_B64.
     * Si la ruta no se puede leer, cae a la variable base64.
     *
     * @return array{client_email:string, private_key:string, token_uri:string}|null
     */
    public static function credentials(): ?array {
        $raw = self::readRawJson();
        if ($raw === null) {
            return null;
        }
        return self::parseCredentials($raw);
    }

    /**
     * Valida un JSON de cuenta de servicio. Público y sin red para poder
     * testearlo offline. Requiere `client_email` y `private_key`.
     *
     * @return array{client_email:string, private_key:string, token_uri:string}|null
     */
    public static function parseCredentials(string $json): ?array {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $email = trim((string) ($data['client_email'] ?? ''));
        $key   = (string) ($data['private_key'] ?? '');
        if ($email === '' || $key === '') {
            return null;
        }

        $tokenUri = trim((string) ($data['token_uri'] ?? ''));
        if ($tokenUri === '') {
            $tokenUri = self::TOKEN_URI;
        }

        return [
            'client_email' => $email,
            'private_key'  => $key,
            'token_uri'    => $tokenUri,
        ];
    }

    /**
     * Construye la aserción JWT (RS256) que se canjea por un access_token.
     *
     * Público y determinista (sin red) para testearlo offline; `$now` permite
     * fijar el instante en los tests.
     *
     * @param array{client_email:string, private_key:string, token_uri?:string} $creds
     */
    public static function buildAssertion(array $creds, ?int $now = null): string {
        $now = $now ?? time();

        $payload = [
            'iss'   => (string) $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => (string) ($creds['token_uri'] ?? self::TOKEN_URI),
            'iat'   => $now,
            'exp'   => $now + self::ASSERTION_TTL,
        ];

        return JWT::encode($payload, (string) $creds['private_key'], 'RS256');
    }

    /**
     * access_token válido: primero desde la caché en disco y, si no hay o
     * expiró, pidiendo uno nuevo a Google. Null si no hay credenciales o el
     * canje falla (el proxy degradará a la rama pública).
     */
    public static function accessToken(): ?string {
        $creds = self::credentials();
        if ($creds === null) {
            return null;
        }

        $cached = self::cachedToken($creds['client_email']);
        if ($cached !== null) {
            return $cached;
        }

        return self::requestToken($creds);
    }

    /** Elimina la caché de tokens (uso en tests / rotación de credenciales). */
    public static function clearCache(): void {
        foreach (glob(self::cacheDir() . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    // ── Interno ─────────────────────────────────────────────────────────────

    /** Lee el JSON crudo de las credenciales según la prioridad configurada. */
    private static function readRawJson(): ?string {
        $file = trim((string) ($_ENV['GOOGLE_SERVICE_ACCOUNT_FILE'] ?? ''));
        if ($file !== '') {
            $path = self::resolvePath($file);
            if ($path !== null) {
                $raw = (string) file_get_contents($path);
                if (trim($raw) !== '') {
                    return $raw;
                }
            }
        }

        $b64 = trim((string) ($_ENV['GOOGLE_SERVICE_ACCOUNT_JSON_B64'] ?? ''));
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded !== false && trim($decoded) !== '') {
                return $decoded;
            }
        }

        return null;
    }

    /** Resuelve una ruta absoluta o relativa a la raíz del proyecto. */
    private static function resolvePath(string $file): ?string {
        if (is_file($file)) {
            return $file;
        }
        $relative = dirname(__DIR__, 2) . '/' . ltrim($file, '/\\');
        return is_file($relative) ? $relative : null;
    }

    /**
     * Pide un access_token a Google canjeando la aserción JWT.
     *
     * @param array{client_email:string, private_key:string, token_uri:string} $creds
     */
    private static function requestToken(array $creds): ?string {
        try {
            $assertion = self::buildAssertion($creds);
        } catch (\Throwable $e) {
            // Nunca se registra la clave ni el contenido de la aserción.
            error_log('[gdrive] No se pudo firmar la aserción JWT: ' . $e->getMessage());
            return null;
        }

        $ch = curl_init($creds['token_uri']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => self::GRANT_TYPE,
                'assertion'  => $assertion,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TOKEN_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'ROMs-Vault/1.0',
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            $detalle = $err !== '' ? $err : 'respuesta no válida';
            error_log('[gdrive] Canje del token OAuth falló (HTTP ' . $code . '): ' . $detalle);
            return null;
        }

        $data  = json_decode((string) $body, true);
        $token = is_array($data) ? (string) ($data['access_token'] ?? '') : '';
        if ($token === '') {
            error_log('[gdrive] Respuesta OAuth sin access_token');
            return null;
        }

        $expiresIn = (int) ($data['expires_in'] ?? self::ASSERTION_TTL);
        $expiresAt = time() + max(60, $expiresIn) - self::TOKEN_MARGIN;
        self::storeToken($creds['client_email'], $token, $expiresAt);

        return $token;
    }

    /** Devuelve el token cacheado si sigue vigente; null si no hay o expiró. */
    private static function cachedToken(string $email): ?string {
        $file = self::cacheDir() . '/' . md5($email) . '.json';
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || empty($data['token']) || (int) ($data['expires_at'] ?? 0) <= time()) {
            @unlink($file);
            return null;
        }

        return (string) $data['token'];
    }

    /** Persiste el token en disco (caché compartida entre peticiones). */
    private static function storeToken(string $email, string $token, int $expiresAt): void {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        file_put_contents(
            $dir . '/' . md5($email) . '.json',
            json_encode(['token' => $token, 'expires_at' => $expiresAt]),
            LOCK_EX
        );
    }

    private static function cacheDir(): string {
        return sys_get_temp_dir() . '/rv_gdrive_token';
    }
}
