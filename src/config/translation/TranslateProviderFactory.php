<?php

require_once __DIR__ . '/TranslationProvider.php';
require_once __DIR__ . '/NullTranslateProvider.php';
require_once __DIR__ . '/DeepLTranslateProvider.php';
require_once __DIR__ . '/GoogleTranslateProvider.php';

/**
 * config/translation/TranslateProviderFactory.php
 * Construye el proveedor de traducción a partir del entorno (.env).
 *
 *   TRANSLATE_PROVIDER    none | deepl | google   (default: none)
 *   TRANSLATE_API_KEY     clave del proveedor (DeepL o Google)
 *   TRANSLATE_TIMEOUT     segundos (default 8)
 *
 * DeepL no necesita servidor ni URL: basta TRANSLATE_API_KEY (plan Free con el
 * endpoint api-free.deepl.com).
 *
 * Cualquier valor desconocido o variable ausente degrada a NullTranslateProvider,
 * de modo que el guardado del contenido nunca se bloquea por falta de proveedor.
 */

class TranslateProviderFactory {
    public static function fromEnv(): TranslationProvider {
        $proveedor = strtolower(trim(self::env('TRANSLATE_PROVIDER', 'none')));
        $timeout   = (int) self::env('TRANSLATE_TIMEOUT', '8');
        if ($timeout <= 0) {
            $timeout = 8;
        }
        $apiKey = self::env('TRANSLATE_API_KEY', '');

        switch ($proveedor) {
            case 'google':
                return new GoogleTranslateProvider($apiKey, $timeout);

            case 'deepl':
                return new DeepLTranslateProvider($apiKey, $timeout);

            case 'none':
            default:
                return new NullTranslateProvider();
        }
    }

    /** Nombres admitidos por TRANSLATE_PROVIDER (para validación en admin/UI). */
    public static function soportados(): array {
        return ['none', 'deepl', 'google'];
    }

    /** Lee una variable de entorno desde $_ENV o getenv(), con valor por defecto. */
    private static function env(string $clave, string $default): string {
        $valor = $_ENV[$clave] ?? getenv($clave);
        if ($valor === false || $valor === '') {
            return $default;
        }
        return (string) $valor;
    }
}
