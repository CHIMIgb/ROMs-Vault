<?php
/**
 * config/IntlHelper.php
 *
 * Helper de internacionalización para fechas y números (PHP 8.2).
 * Usa el idioma activo de I18n::getLang() (es/en) y formatea con la
 * extensión intl cuando está disponible.
 *
 * IMPORTANTE: la extensión `intl` es OPCIONAL. Todo el código —incluidos los
 * valores por defecto de los parámetros— debe funcionar SIN ella. Por eso no
 * se referencia nunca `IntlDateFormatter::*` fuera de un bloque protegido por
 * class_exists(); las constantes de estilo se definen aquí con los mismos
 * valores que usa Intl, de modo que son intercambiables al construir el
 * formateador.
 *
 * NOTA: No gestiona moneda (no existe en la app). Los tamaños de archivo
 * conservan sus unidades (B/KB/MB/GB) y solo se formatea el valor numérico.
 */
class IntlHelper
{
    // ── Estilos de fecha/hora (mismos valores que IntlDateFormatter) ─────────
    public const FULL   = 0;
    public const LONG   = 1;
    public const MEDIUM = 2;
    public const SHORT  = 3;
    public const NONE   = -1;

    /**
     * Locale Intl por idioma activo (coincide con I18n::LANGUAGES).
     * Si intl no está disponible, la clave se usa para elegir el formato
     * del fallback (meses y separadores numéricos).
     */
    private const LOCALES = [
        'es' => 'es_ES',
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'pt' => 'pt_BR',
        'de' => 'de_DE',
        'ja' => 'ja_JP',
        'it' => 'it_IT',
        'ru' => 'ru_RU',
        'tr' => 'tr_TR',
    ];

    /**
     * Código de idioma que espera EmulatorJS en `window.EJS_language` según el
     * idioma activo. Debe corresponder a un fichero real del CDN
     * (cdn.emulatorjs.org/stable/data/localization/<código>.json).
     *
     * OJO: EmulatorJS NO publica francés (`af-FR` es afrikáans, no français),
     * así que 'fr' cae a 'en-US' (fallback neutro).
     */
    private const EMULATOR_LOCALES = [
        'es' => 'es-ES',
        'en' => 'en-US',
        'fr' => 'en-US',
        'pt' => 'pt-BR',
        'de' => 'de-GER',
        'ja' => 'ja-JA',
        'it' => 'it-IT',
        'ru' => 'ru-RU',
        'tr' => 'tr-TR',
    ];

    /**
     * Devuelve el locale para Intl según idioma activo.
     * @return string p. ej. 'es_ES', 'en_US', 'fr_FR', 'pt_BR', 'de_DE', 'ja_JP', 'it_IT', 'ru_RU', 'tr_TR'
     */
    public static function getLocale(): string
    {
        if (class_exists('I18n')) {
            $lang = I18n::getLang();
            if (isset(self::LOCALES[$lang])) {
                return self::LOCALES[$lang];
            }
        }
        return self::LOCALES['es'];
    }

    /**
     * Idioma de la interfaz de EmulatorJS (`window.EJS_language`) según el
     * idioma activo de la app.
     *
     * Siempre devuelve un código publicado por el CDN de EmulatorJS; si el
     * idioma activo no tuviera traducción propia, cae a 'en-US'.
     *
     * @return string p. ej. 'es-ES', 'en-US', 'de-GER', 'ja-JA', 'pt-BR'
     */
    public static function emulatorLanguage(): string
    {
        if (class_exists('I18n')) {
            $lang = I18n::getLang();
            if (isset(self::EMULATOR_LOCALES[$lang])) {
                return self::EMULATOR_LOCALES[$lang];
            }
        }
        return 'en-US';
    }

    /**
     * Formatea una fecha (timestamp, string ISO/fecha o DateTime).
     *
     * @param int|string|\DateTimeInterface|null $date
     * @param int $dateStyle  self::FULL|LONG|MEDIUM|SHORT|NONE
     * @param int $timeStyle  self::FULL|LONG|MEDIUM|SHORT|NONE
     */
    public static function formatDate($date = null, int $dateStyle = self::MEDIUM, int $timeStyle = self::NONE): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        try {
            $dt = self::toDateTime($date);
        } catch (\Throwable $e) {
            $dt = null;
        }

        // Si no pudimos interpretarlo, intento bruto sin Intl.
        if ($dt === null) {
            $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
            if ($ts === false || $ts === null) {
                return (string) $date;
            }
            $dt = (new \DateTimeImmutable())->setTimestamp($ts);
        }

        // Intl disponible: formato nativo según locale.
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter(
                self::getLocale(),
                $dateStyle,
                $timeStyle,
                null,
                \IntlDateFormatter::GREGORIAN
            );
            if ($fmt !== false) {
                $res = $fmt->format($dt);
                if ($res !== false) {
                    return $res;
                }
            }
        }

        // Fallback sin intl: respeta el idioma activo.
        return self::fallbackDate($dt, $dateStyle, $timeStyle);
    }

    /**
     * Formatea solo el año (numérico) según locale (coherente con i18n).
     *
     * @param int|string|\DateTimeInterface|null $date
     */
    public static function formatYear($date = null): string
    {
        try {
            $dt = self::toDateTime($date);
        } catch (\Throwable $e) {
            $dt = null;
        }

        if ($dt === null) {
            if (is_numeric($date)) {
                return (string) $date;
            }
            $ts = strtotime((string) $date);
            return $ts === false ? (string) $date : date('Y', $ts);
        }

        // El año es numérico: no depende de Intl ni del locale.
        return $dt->format('Y');
    }

    /**
     * Formatea un número según locale (separador de miles/decimales).
     *
     * @param int|float|string|null $number
     * @param int $decimals  Número de decimales (0 = entero)
     */
    public static function formatNumber($number, int $decimals = 0): string
    {
        if ($number === null || $number === '' || !is_numeric($number)) {
            $number = 0;
        }
        $number = (float) $number;

        if (class_exists('NumberFormatter')) {
            $fmt = new \NumberFormatter(self::getLocale(), \NumberFormatter::DECIMAL);
            if ($fmt !== false) {
                $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
                $res = $fmt->format($number);
                if ($res !== false) {
                    return $res;
                }
            }
        }

        // Fallback: es/de/pt → 1.234.567,89 ; en/ja → 1,234,567.89 ; fr → 1 234 567,89
        [$dec, $group] = self::fallbackSeparators();
        return number_format($number, $decimals, $dec, $group);
    }

    /**
     * Bytes → B/KB/MB/GB, formateando el número según locale.
     *
     * @param int|float|string|null $bytes
     */
    public static function formatBytes($bytes): string
    {
        if ($bytes === null || $bytes === '' || !is_numeric($bytes)) {
            return '0 B';
        }
        $bytes = (float) $bytes;

        foreach ([
            ['GB', 1073741824, 2],
            ['MB', 1048576,    2],
            ['KB', 1024,       1],
        ] as [$unit, $factor, $decimals]) {
            if ($bytes >= $factor) {
                return self::formatNumber($bytes / $factor, $decimals) . ' ' . $unit;
            }
        }

        return self::formatNumber($bytes, 0) . ' B';
    }

    /**
     * Convierte varios formatos a DateTimeImmutable.
     *
     * @param int|string|\DateTimeInterface $date
     * @return \DateTimeImmutable|null
     */
    private static function toDateTime($date): ?\DateTimeImmutable
    {
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }
        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date);
        }
        if (is_int($date)) {
            return (new \DateTimeImmutable())->setTimestamp($date);
        }
        if (is_string($date)) {
            $date = trim($date);
            if ($date === '') {
                return null;
            }
            // Formato de la BD (DATE) y variantes ISO.
            foreach (['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s'] as $format) {
                $dt = \DateTimeImmutable::createFromFormat($format, $date);
                if ($dt instanceof \DateTimeImmutable) {
                    return $dt;
                }
            }
            $ts = strtotime($date);
            if ($ts !== false) {
                return (new \DateTimeImmutable())->setTimestamp($ts);
            }
        }
        return null;
    }

    /**
     * Separadores del fallback numérico según locale: [decimal, miles].
     * Coincide con el formato local de cada idioma cuando intl no está.
     */
    private static function fallbackSeparators(): array
    {
        switch (self::getLocale()) {
            case 'en_US':
            case 'ja_JP':
                return ['.', ','];
            case 'fr_FR':
                return [',', "\u{202f}"]; // espacio fino irrompible
            case 'ru_RU':
                return [',', "\u{00a0}"]; // espacio irrompible como separador de miles
            case 'de_DE':
            case 'es_ES':
            case 'pt_BR':
            case 'it_IT':
            case 'tr_TR':
            default:
                return [',', '.'];
        }
    }

    /**
     * Nombres de mes (abreviados) por locale para el fallback sin intl.
     */
    private static function fallbackMonths(): array
    {
        return [
            'es_ES' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
            'en_US' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'fr_FR' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            'pt_BR' => ['jan.', 'fev.', 'mar.', 'abr.','mai.','jun.','jul.','ago.','set.','out.','nov.','dez.'],
            'de_DE' => ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'],
            'ja_JP' => ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
            'it_IT' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
            'ru_RU' => ['янв.', 'фев.', 'мар.', 'апр.', 'мая', 'июн.', 'июл.', 'авг.', 'сен.', 'окт.', 'ноя.', 'дек.'],
            'tr_TR' => ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
        ];
    }

    /**
     * Formato de fecha/hora sin la extensión intl, respetando el idioma activo.
     * Cubre los estilos que usa la aplicación (MEDIUM/SHORT + NONE).
     */
    private static function fallbackDate(\DateTimeImmutable $dt, int $dateStyle, int $timeStyle): string
    {
        $locale = self::getLocale();
        $d = (int) $dt->format('j');
        $m = (int) $dt->format('n');
        $y = (int) $dt->format('Y');

        $months = self::fallbackMonths();
        $mon = $months[$locale][$m - 1] ?? $months['en_US'][$m - 1];

        // Orden de la fecha larga/media: ja usa y/m/d; en usa "{mes} d, y"; el resto d mes y.
        switch ($locale) {
            case 'en_US':
                $longDate = $mon . ' ' . $d . ', ' . $y;
                break;
            case 'ja_JP':
                $longDate = $y . '/' . $m . '/' . $d;
                break;
            default: // es, fr, pt, de, it, ru, tr
                $longDate = $d . ' ' . $mon . ' ' . $y;
        }

        // Fecha corta: en m/d/y, ja y/m/d, el resto d/m/y.
        $shortDate = match ($locale) {
            'en_US' => sprintf('%02d/%02d/%04d', $m, $d, $y),
            'ja_JP' => sprintf('%04d/%02d/%02d', $y, $m, $d),
            default => sprintf('%02d/%02d/%04d', $d, $m, $y),
        };

        $date = '';
        switch ($dateStyle) {
            case self::FULL:
            case self::LONG:
            case self::MEDIUM:
                $date = $longDate;
                break;
            case self::SHORT:
                $date = $shortDate;
                break;
            case self::NONE:
            default:
                $date = '';
        }

        $time = '';
        switch ($timeStyle) {
            case self::FULL:
            case self::LONG:
            case self::MEDIUM:
                $time = $dt->format('H:i:s');
                break;
            case self::SHORT:
                $time = $dt->format('H:i');
                break;
            case self::NONE:
            default:
                $time = '';
        }

        return trim($date . ($date !== '' && $time !== '' ? ' ' : '') . $time);
    }
}
