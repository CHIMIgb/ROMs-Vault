<?php
/**
 * config/translation/TranslationProvider.php
 * Contrato común de los proveedores de traducción automática (i18n, bloque 2).
 *
 * Un proveedor traduce un lote de textos de un idioma origen a un idioma
 * destino. La aplicación NO depende de un proveedor concreto: la fábrica
 * (TranslateProviderFactory) decide cuál usar según el entorno y, si no hay
 * ninguno configurado, se usa NullTranslateProvider (no-op). Así el guardado
 * de contenido nunca se bloquea por falta de traductor.
 */

interface TranslationProvider {

    /** Nombre corto del proveedor (para logs y UI): none | deepl | google. */
    public function name(): string;

    /** true si el proveedor tiene lo mínimo para operar (URL o API key). */
    public function isConfigured(): bool;

    /**
     * Traduce un lote de textos de $source a $target.
     *
     * @param array<string,string> $textos Mapa clave => texto (p. ej. 'nombre' => 'Acción').
     * @param string $source Código ISO de origen (normalmente 'es').
     * @param string $target Código ISO de destino.
     * @return array<string,string> El mismo mapa con los textos traducidos. Los textos
     *                             vacíos o que fallen se devuelven sin cambios.
     */
    public function translateBatch(array $textos, string $source, string $target): array;
}
