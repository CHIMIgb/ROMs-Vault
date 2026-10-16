<?php
/**
 * config/GDriveClient.php
 * Cliente mínimo de la Google Drive API para el proxy de streaming.
 *
 * Centraliza la decisión «API vs público» para no dispersarla por el proxy:
 * con una cuenta de servicio configurada (GoogleServiceAccount), el proxy pide
 * el binario a la Drive API (archivos privados); sin ella, mantiene el flujo
 * público actual. Ver docs/planificado/2026-10-14-drive-privado-service-account.md.
 *
 * En modo API la URL de descarga es:
 *   GET https://www.googleapis.com/drive/v3/files/{id}?alt=media&supportsAllDrives=true
 * con la cabecera Authorization: Bearer <access_token>. La Drive API soporta
 * Range Requests (206), necesario para el seeking del emulador.
 */

require_once __DIR__ . '/GoogleServiceAccount.php';

class GDriveClient {

    /** Endpoint base de archivos de la Drive API v3. */
    private const FILES_ENDPOINT = 'https://www.googleapis.com/drive/v3/files/';

    /** ¿Está habilitado el modo Drive API (cuenta de servicio configurada)? */
    public static function enabled(): bool {
        return GoogleServiceAccount::isConfigured();
    }

    /** URL de descarga del binario (alt=media) para un file_id de Drive. */
    public static function mediaUrl(string $fileId): string {
        return self::FILES_ENDPOINT . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true';
    }

    /**
     * Cabeceras de autenticación para la Drive API.
     * Devuelve [] si no se pudo obtener un access_token (el llamador debe
     * degradar a la rama pública).
     *
     * @return string[]
     */
    public static function authHeaders(): array {
        $token = GoogleServiceAccount::accessToken();
        return $token === null ? [] : ['Authorization: Bearer ' . $token];
    }
}
