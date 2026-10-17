<?php

/**
 * config/SecurityHeaders.php
 * Cabeceras de seguridad globales de las páginas HTML (CSP incluida).
 *
 * Antes vivían inline en index.php; ahora la CSP se compone desde un único
 * punto para cada host externo (EmulatorJS::CDN, GoogleFonts::*) y desde el host
 * público de R2 (R2_PUBLIC_HOST, con reserva a R2_PUBLIC_BASE).
 *
 * La política es deliberadamente permisiva con EmulatorJS: 'unsafe-eval' y
 * blob: son necesarios para el runtime de Emscripten (cwrap/new Function y el
 * core descomprimido como Object URL). NO apretarla sin probar el emulador;
 * ver docs/2026-08-03-c2-seguridad-ip-descargas-csp.md.
 */

require_once __DIR__ . '/EmulatorJS.php';
require_once __DIR__ . '/GoogleFonts.php';

class SecurityHeaders {
    /**
     * Host público de R2 para img-src (" https://host"), o cadena vacía si no
     * hay ninguno configurado. Las portadas/capturas se sirven desde R2 y el
     * navegador las carga directo, así que la CSP debe permitir ese host.
     */
    private static function r2ImgSrc(): string {
        $raw = $_ENV['R2_PUBLIC_HOST'] ?? getenv('R2_PUBLIC_HOST');
        if (empty($raw)) {
            $raw = $_ENV['R2_PUBLIC_BASE'] ?? getenv('R2_PUBLIC_BASE');
        }
        if (empty($raw)) {
            return '';
        }

        $url  = strpos((string) $raw, '://') !== false ? (string) $raw : 'https://' . $raw;
        $host = parse_url($url, PHP_URL_HOST);

        return empty($host) ? '' : ' https://' . $host;
    }

    /** Política de seguridad de contenido (CSP) de las páginas HTML. */
    public static function csp(): string {
        $emulator = EmulatorJS::CDN;

        return "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: " . $emulator . "; "
            . "style-src 'self' 'unsafe-inline' " . GoogleFonts::CSS_ORIGIN . " " . $emulator . "; "
            . "font-src 'self' " . GoogleFonts::FILES_ORIGIN . " data: " . $emulator . "; "
            . "img-src 'self' data: blob: " . $emulator . self::r2ImgSrc() . "; "
            . "media-src 'self' blob: " . $emulator . "; "
            . "connect-src 'self' blob: " . $emulator . "; "
            . "worker-src 'self' blob: " . $emulator . "; "
            . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";
    }

    /** Emite las cabeceras de seguridad globales de las páginas HTML. */
    public static function emitir(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Content-Security-Policy: ' . self::csp());
    }
}
