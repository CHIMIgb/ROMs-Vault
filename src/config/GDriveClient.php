<?php
/**
 * config/GDriveClient.php
 * Cliente mínimo de la Google Drive API para el proxy de streaming.
 *
 * Centraliza la decisión «API vs público» para no dispersarla por el proxy. El
 * modo se elige EXPLÍCITAMENTE con la variable de entorno GDRIVE_MODE:
 *   - 'api' (por defecto): pide el binario a la Drive API (archivos privados).
 *   - 'public'          : mantiene el flujo público de Drive (dev, CI, rollback).
 * No hay degradación automática: con GDRIVE_MODE=api y sin credenciales (o sin
 * access_token), el proxy responde un error claro en lugar de caer a la vía
 * pública. Ver docs/planificado/2026-10-14-drive-privado-service-account.md.
 *
 * En modo API la URL de descarga es:
 *   GET https://www.googleapis.com/drive/v3/files/{id}?alt=media&supportsAllDrives=true
 * con la cabecera Authorization: Bearer <access_token>. La Drive API soporta
 * Range Requests (206), necesario para el seeking del emulador.
 */

require_once __DIR__ . '/GoogleServiceAccount.php';

class GDriveClient {

    /** Modo Drive API (archivos privados): valor por defecto. */
    public const MODE_API = 'api';

    /** Modo público (enlace de Drive sin credenciales): dev / CI / rollback. */
    public const MODE_PUBLIC = 'public';

    /** Endpoint base de archivos de la Drive API v3. */
    private const FILES_ENDPOINT = 'https://www.googleapis.com/drive/v3/files/';

    /**
     * Modo de acceso a Drive (GDRIVE_MODE). Solo el valor 'public' activa el
     * flujo público; cualquier otro valor (o ausente) usa la Drive API por
     * defecto, de modo que producción nunca degrada en silencio.
     */
    public static function mode(): string {
        $raw  = $_ENV['GDRIVE_MODE'] ?? getenv('GDRIVE_MODE');
        $mode = strtolower(trim((string) ($raw === false ? '' : $raw)));

        return $mode === self::MODE_PUBLIC ? self::MODE_PUBLIC : self::MODE_API;
    }

    /**
     * ¿Se usa la Drive API? Solo en modo API y con la cuenta de servicio
     * configurada. Si devuelve false NO significa «degradar a público»: con
     * GDRIVE_MODE=api es un error de configuración que el proxy reporta (503).
     */
    public static function enabled(): bool {
        return self::mode() === self::MODE_API && GoogleServiceAccount::isConfigured();
    }

    /** URL de descarga del binario (alt=media) para un file_id de Drive. */
    public static function mediaUrl(string $fileId): string {
        return self::FILES_ENDPOINT . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true';
    }

    /**
     * Cabeceras de autenticación para la Drive API.
     * Devuelve [] si no se pudo obtener un access_token; en modo API eso es un
     * error de configuración (el proxy responde 503), nunca un fallback.
     *
     * @return string[]
     */
    public static function authHeaders(): array {
        $token = GoogleServiceAccount::accessToken();
        return $token === null ? [] : ['Authorization: Bearer ' . $token];
    }
}
