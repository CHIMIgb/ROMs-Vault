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
     * Devuelve el locale para Intl según idioma activo.
     * @return string 'es_ES' o 'en_US'
     */
    public static function getLocale(): string
    {
        if (class_exists('I18n')) {
            $lang = I18n::getLang();
            if ($lang === 'en') {
                return 'en_US';
            }
        }
        return 'es_ES';
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

        // Fallback: es → 1.234.567,89 ; en → 1,234,567.89
        $isEn = self::getLocale() === 'en_US';
        return number_format($number, $decimals, $isEn ? '.' : ',', $isEn ? ',' : '.');
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
     * Formato de fecha/hora sin la extensión intl, respetando es/en.
     * Cubre los estilos que usa la aplicación (MEDIUM/SHORT + NONE).
     */
    private static function fallbackDate(\DateTimeImmutable $dt, int $dateStyle, int $timeStyle): string
    {
        $isEn = self::getLocale() === 'en_US';

        $monthsEs = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $monthsEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $m = (int) $dt->format('n');
        $d = $dt->format('j');
        $y = $dt->format('Y');

        $date = '';
        switch ($dateStyle) {
            case self::FULL:
            case self::LONG:
            case self::MEDIUM:
                $date = $isEn
                    ? $monthsEn[$m - 1] . ' ' . $d . ', ' . $y
                    : $d . ' ' . $monthsEs[$m - 1] . ' ' . $y;
                break;
            case self::SHORT:
                $date = $isEn ? $dt->format('m/d/Y') : $dt->format('d/m/Y');
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
