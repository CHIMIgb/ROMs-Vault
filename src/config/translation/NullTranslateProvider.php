<?php
require_once __DIR__ . '/TranslationProvider.php';

/**
 * config/translation/NullTranslateProvider.php
 * Proveedor no-op: se usa cuando no hay ninguno configurado.
 *
 * Devuelve el texto original sin cambios, de modo que el contenido queda en
 * 'es' y la aplicación funciona con normalidad (degradación segura).
 */

class NullTranslateProvider implements TranslationProvider {

    public function name(): string {
        return 'none';
    }

    public function isConfigured(): bool {
        return false;
    }

    public function translateBatch(array $textos, string $source, string $target): array {
        return $textos;
    }
}
