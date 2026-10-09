<?php
/**
 * config/i18n.php — Cargador de diccionarios y traducción (ítem 6.1 del plan).
 *
 * - Diccionarios en JSON bajo src/i18n/{es,en}.json (clave → texto). JSON no
 *   ejecuta código: más seguro si algún día se editan desde administración.
 * - Función global t('clave', params) con placeholders {nombre}.
 * - Fallback: clave ausente en el idioma activo → se busca en 'es' → si tampoco,
 *   se devuelve la propia clave (visible para el desarrollador).
 * - El idioma activo por defecto es 'es' (I18N_LANG). La detección por cookie /
 *   Accept-Language (ítem 6.2) usará setLang() sin tocar este cargador.
 *
 * Los datos de la BD (títulos de juegos, consolas, categorías) NO se traducen:
 * son contenido, no interfaz (decisión documentada en el plan i18n).
 */

class I18n {

    /** Idiomas soportados (whitelist anti path traversal y anti inyección). */
    public const LANGUAGES = ['es', 'en'];

    /** Idioma por defecto (fallback universal). */
    public const DEFAULT_LANG = 'es';

    /** Cache de diccionarios ya cargados: lang => array clave => texto. */
    private static array $dictionaries = [];

    /** Idioma activo de la petición. */
    private static string $lang = '';

    /**
     * Inicializa el cargador con el idioma activo.
     * Se llama desde index.php (todas las páginas HTML) y desde los tests.
     */
    public static function init(): void {
        $requested = $_ENV['I18N_LANG'] ?? self::DEFAULT_LANG;
        self::setLang(is_string($requested) ? $requested : self::DEFAULT_LANG);
    }

    /**
     * Cambia el idioma activo. Devuelve false si el idioma no está soportado
     * (se mantiene el anterior y se registra el intento inválido).
     */
    public static function setLang(string $lang): bool {
        if (!in_array($lang, self::LANGUAGES, true)) {
            error_log('[i18n] Idioma no soportado ignorado: ' . $lang);
            return false;
        }

        self::load($lang);
        self::$lang = $lang;
        return true;
    }

    /** Idioma activo ('es' por defecto). */
    public static function getLang(): string {
        if (self::$lang === '') {
            self::init();
        }
        return self::$lang;
    }

    /**
     * Traduce una clave con params. Siempre devuelve string (nunca lanza).
     *
     * @param string $key    Clave del diccionario (dot-notation: "nav.catalogo")
     * @param array  $params Valores para placeholders {nombre}
     */
    public static function translate(string $key, array $params = []): string {
        $lang   = self::getLang();
        $text   = self::dictionary($lang)[$key]
               ?? self::dictionary(self::DEFAULT_LANG)[$key]
               ?? $key;

        // Placeholders {nombre} → valor (strtr no interpeta caracteres raros)
        if ($params !== [] && str_contains($text, '{')) {
            $replace = [];
            foreach ($params as $name => $value) {
                $replace['{' . $name . '}'] = (string) $value;
            }
            $text = strtr($text, $replace);
        }

        return $text;
    }

    /** Diccionario completo del idioma indicado (array clave → texto). */
    public static function dictionary(string $lang): array {
        self::load($lang);
        return self::$dictionaries[$lang] ?? [];
    }

    /**
     * Carga (una sola vez) el JSON del idioma y lo valida.
     * Fallo de lectura/decode → error_log + diccionario vacío (degradación
     * segura: t() devolvería la clave, que es visible y depurable).
     */
    private static function load(string $lang): void {
        if (isset(self::$dictionaries[$lang])) {
            return;
        }
        self::$dictionaries[$lang] = [];

        $file = __DIR__ . '/../i18n/' . $lang . '.json';
        $raw  = @file_get_contents($file);
        if ($raw === false) {
            error_log('[i18n] No se pudo leer el diccionario: ' . $file);
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            error_log('[i18n] JSON inválido en ' . $file . ': ' . json_last_error_msg());
            return;
        }

        // Validar tipo: solo claves string → textos string (defensa)
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $clean[$key] = $value;
            }
        }
        self::$dictionaries[$lang] = $clean;
    }
}

/**
 * Función global de traducción. Uso en vistas/controladores:
 *   <?= t('nav.catalogo') ?>
 *   <?= t('common.saludo', ['nombre' => $usuario]) ?>
 */
function t(string $key, array $params = []): string {
    return I18n::translate($key, $params);
}