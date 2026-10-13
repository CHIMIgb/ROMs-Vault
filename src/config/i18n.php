<?php
/**
 * config/i18n.php — Cargador de diccionarios y traducción (ítem 6.1 del plan).
 *
 * - Diccionarios en JSON bajo src/i18n/{es,en,fr,pt,de,ja,it,ru,tr}.json (clave → texto). JSON no
 *   ejecuta código: más seguro si algún día se editan desde administración.
 * - Función global t('clave', params) con placeholders {nombre}.
 * - Fallback: clave ausente en el idioma activo → se busca en 'es' → si tampoco,
 *   se devuelve la propia clave (visible para el desarrollador).
 * - El idioma activo por defecto es 'es' (I18N_LANG). La detección por cookie /
 *   Accept-Language (ítem 6.2) usará setLang() sin tocar este cargador.
 *
 * Contenido de la BD: desde el bloque 2 de i18n, los campos editoriales de la
 * BD (categorias.nombre/descripcion, juegos.descripcion) SÍ se traducen, vía
 * TraduccionService + tablas *_traducciones, con fallback a 'es'. Este cargador
 * solo cubre la INTERFAZ. Los nombres propios (juegos.titulo, consolas.*,
 * emuladores.nombre) siguen sin traducirse.
 */

class I18n {

    /** Idiomas soportados (whitelist anti path traversal y anti inyección). */
    public const LANGUAGES = ['es', 'en', 'fr', 'pt', 'de', 'ja', 'it', 'ru', 'tr', 'zh', 'ko', 'ar'];

    /**
     * Idiomas con escritura de derecha a izquierda (RTL).
     * El atributo dir="rtl" se aplica solo para estos idiomas (FASE 5.2).
     */
    public const RTL_LANGS = ['ar'];

    /** Idioma por defecto (fallback universal). */
    public const DEFAULT_LANG = 'es';

    /** Cache de diccionarios ya cargados: lang => array clave => texto. */
    private static array $dictionaries = [];

    /** Idioma activo de la petición. */
    private static string $lang = '';

    /**
     * Inicializa el cargador con el idioma activo de la petición.
     * Se llama desde index.php (todas las páginas HTML) y desde los tests.
     */
    public static function init(): void {
        self::setLang(self::detectLangFromRequest());
    }

    /**
     * Resuelve el idioma activo de la petición (ítem 6.2):
     *   1. Cookie rv_lang (preferencia persistida por el selector ?lang=)
     *   2. Cabecera Accept-Language (primer idioma soportado entre los de LANGUAGES)
     *   3. I18N_LANG del entorno (.env) — base del sitio
     *   4. Default 'es'
     */
    public static function detectLangFromRequest(): string {
        // 1) Cookie persistida
        $cookie = $_COOKIE['rv_lang'] ?? '';
        if (is_string($cookie) && in_array($cookie, self::LANGUAGES, true)) {
            return $cookie;
        }

        // 2) Accept-Language: "es-ES,es;q=0.9,en;q=0.8" → primer idioma soportado
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (is_string($accept) && $accept !== '') {
            foreach (explode(',', $accept) as $part) {
                $code = strtolower(trim(explode(';', $part)[0]));
                $lang = explode('-', $code)[0]; // 'en-US' → 'en'
                if (in_array($lang, self::LANGUAGES, true)) {
                    return $lang;
                }
            }
        }

        // 3) Base del sitio desde entorno
        $env = $_ENV['I18N_LANG'] ?? self::DEFAULT_LANG;
        if (is_string($env) && in_array($env, self::LANGUAGES, true)) {
            return $env;
        }

        return self::DEFAULT_LANG;
    }

    /**
     * Persiste la preferencia de idioma en la cookie rv_lang (1 año).
     * No lleva __Host- ni HttpOnly: no es dato sensible y el selector
     * funciona sin JavaScript; SameSite=Lax evita envío en contextos raros.
     */
    public static function setLangCookie(string $lang): void {
        if (!in_array($lang, self::LANGUAGES, true)) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('rv_lang', $lang, [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Resetea el estado estático (idioma + cache de diccionarios).
     * Uso exclusivo en tests para no contaminar entre casos.
     */
    public static function reset(): void {
        self::$lang = '';
        self::$dictionaries = [];
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
     * Indica si el idioma indicado (o el activo) usa escritura RTL.
     * @param string|null $lang Código de idioma; null = idioma activo.
     */
    public static function isRtl(?string $lang = null): bool {
        $code = $lang ?? self::getLang();
        return in_array($code, self::RTL_LANGS, true);
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