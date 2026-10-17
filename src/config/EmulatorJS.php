<?php

/**
 * config/EmulatorJS.php
 * Origen único del CDN de EmulatorJS (vista de juego y CSP).
 *
 * La ruta de datos del CDN se usa en dos puntos de src/views/home/play.php
 * (window.EJS_pathtodata y el <script> del loader) y el origen se permite en la
 * CSP (src/config/SecurityHeaders.php). Tenerlos en un único sitio evita que un
 * cambio del CDN —o su autoalojamiento en /public/vendor/— deje una de las
 * copias sin actualizar y rompa el emulador en silencio.
 *
 * Ver docs/2026-10-16-centralizacion-hosts-externos.md.
 */

class EmulatorJS {
    /** Origen del CDN (se permite tal cual en la CSP). */
    public const CDN = 'https://cdn.emulatorjs.org';

    /** Ruta de la distribución estable dentro del CDN. */
    public const DATA_PATH = '/stable/data/';

    /** Directorio de datos que espera EmulatorJS (window.EJS_pathtodata). */
    public static function dataUrl(): string {
        return self::CDN . self::DATA_PATH;
    }

    /** Loader que la página de juego inyecta como <script>. */
    public static function loaderUrl(): string {
        return self::dataUrl() . 'loader.js';
    }
}
