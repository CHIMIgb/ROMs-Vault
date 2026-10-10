<?php
/**
 * config/IntlHelper.php
 *
 * Helper de internacionalización para fechas y números con Intl (PHP 8.2).
 * Usa el idioma activo de I18n::getLang() (es/en). Con fallback seguro a
 * date()/number_format() si la extensión intl no está disponible.
 *
 * NOTA: No gestiona moneda (no existe en la app). Los tamaños de archivo
 * conservan sus unidades (B/KB/MB/GB) y solo se formatea el valor numérico.
 */
class IntlHelper
{
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
     * @param int $dateStyle  IntlDateFormatter::NONE|SHORT|MEDIUM|LONG|FULL
     * @param int $timeStyle  IntlDateFormatter::NONE|SHORT|MEDIUM|LONG|FULL
     */
    public static function formatDate($date = null, int $dateStyle = IntlDateFormatter::MEDIUM, int $timeStyle = IntlDateFormatter::NONE): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        try {
            $dt = self::toDateTime($date);
        } catch (\Throwable $e) {
            $dt = null;
        }

        if ($dt === null) {
            // Fallback bruto
            if (is_numeric($date)) {
                return date('d/m/Y', (int) $date);
            }
            $ts = strtotime((string) $date);
            return $ts === false ? (string) $date : date('d/m/Y', $ts);
        }

        // Intl disponible
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter(
                self::getLocale(),
                $dateStyle,
                $timeStyle,
                null,
                IntlDateFormatter::GREGORIAN
            );
            if ($fmt !== false) {
                $res = $fmt->format($dt);
                if ($res !== false) {
                    return $res;
                }
            }
        }

        // Fallback: formato español neutro por defecto
        return $dt->format('d/m/Y');
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
            return $ts === false ? '' : (string) date('Y', $ts);
        }

        // Año numérico (no varía entre locales). Usamos Intl con patrón y para robustez
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter(
                self::getLocale(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                null,
                IntlDateFormatter::GREGORIAN,
                'yyyy'
            );
            if ($fmt !== false) {
                $res = $fmt->format($dt);
                if ($res !== false) {
                    return $res;
                }
            }
        }
        return $dt->format('Y');
    }

    /**
     * Formatea un número con separadores de miles según locale.
     *
     * @param int|float $num
     */
    public static function formatNumber($num, int $decimals = 0): string
    {
        if ($num === null) {
            return '0';
        }
        if (!is_numeric($num)) {
            $num = 0;
        }

        $value = (float) $num;
        $isInt = ($decimals <= 0);

        if (class_exists('NumberFormatter')) {
            $fmt = new NumberFormatter(self::getLocale(), NumberFormatter::DEFAULT_STYLE);
            if ($fmt !== false) {
                if ($isInt) {
                    // Enteros sin decimales
                    $res = $fmt->format((int) round($value));
                } else {
                    $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                    $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
                    $res = $fmt->format($value);
                }
                if ($res !== false) {
                    return $res;
                }
            }
        }

        // Fallback
        return number_format($value, $decimals, '.', ',');
    }

    /**
     * Formatea bytes a B/KB/MB/GB con separadores según locale.
     *
     * @param int|float|null $bytes
     */
    public static function formatBytes($bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return '0 B';
        }
        if (!is_numeric($bytes)) {
            $bytes = 0;
        }
        $bytesFloat = (float) $bytes;

        if ($bytesFloat >= 1073741824) {
            return self::formatNumber($bytesFloat / 1073741824, 2) . ' GB';
        }
        if ($bytesFloat >= 1048576) {
            return self::formatNumber($bytesFloat / 1048576, 2) . ' MB';
        }
        if ($bytesFloat >= 1024) {
            return self::formatNumber($bytesFloat / 1024, 1) . ' KB';
        }
        return self::formatNumber($bytesFloat, 0) . ' B';
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
            $s = trim($date);
            if ($s === '') {
                return null;
            }
            // Intentar formatos comunes (DATE, DATETIME, ISO)
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s);
            if ($dt !== false) {
                return $dt;
            }
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s);
            if ($dt !== false) {
                return $dt;
            }
            $dt = \DateTimeImmutable::createFromFormat(DATE_ATOM, $s);
            if ($dt !== false) {
                return $dt;
            }
            // strtotime
            $ts = strtotime($s);
            if ($ts !== false) {
                return (new \DateTimeImmutable())->setTimestamp($ts);
            }
        }
        return null;
    }
}
