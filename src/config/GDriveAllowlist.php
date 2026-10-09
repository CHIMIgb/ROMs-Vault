<?php
/**
 * config/GDriveAllowlist.php
 * Allowlist de hosts para el proxy de streaming (anti-SSRF, ítem 3.2 / A10).
 *
 * El proxy (endpoints/rom_proxy.php) solo puede seguir redirecciones y conectar
 * a hosts de Google Drive listados aquí; cualquier Location a otro host se corta
 * sin llegar a conectar (protege red interna, metadata cloud, etc.).
 *
 * La lista es configurable en .env con GDRIVE_ALLOWED_HOSTS (coma-separada,
 * sin espacios). Si está vacía/ausente se usan los defaults del proyecto
 * (drive.google.com y drive.usercontent.google.com), de modo que los hosts de
 * entrega futuros se pueden añadir sin tocar código.
 */

class GDriveAllowlist {

    /** Hosts de entrega de Google Drive permitidos por defecto */
    public const HOSTS_DEFAULT = [
        'drive.google.com',
        'drive.usercontent.google.com',
    ];

    /**
     * Lista de hosts permitidos, normalizada (minúsculas, sin espacios).
     * Lee $_ENV['GDRIVE_ALLOWED_HOSTS'] (coma-separada); vacío/ausente → defaults.
     *
     * @return string[]
     */
    public static function hostsPermitidos(): array {
        $raw = trim((string) ($_ENV['GDRIVE_ALLOWED_HOSTS'] ?? ''));
        if ($raw === '') {
            return self::HOSTS_DEFAULT;
        }

        $hosts = [];
        foreach (explode(',', $raw) as $host) {
            $host = strtolower(trim($host));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        return $hosts ?: self::HOSTS_DEFAULT;
    }

    /**
     * ¿El host está permitido?
     * Comparación EXACTA e insensitive a mayúsculas (sin subdominios, sin
     * trailing dot, sin IPs que no estén listadas explícitamente).
     */
    public static function hostPermitido(string $host): bool {
        $host = strtolower(trim($host));
        return $host !== '' && in_array($host, self::hostsPermitidos(), true);
    }
}