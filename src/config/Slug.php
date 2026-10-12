<?php

/**
 * src/config/Slug.php
 * Generación y unicidad de slugs para URLs limpias (ítem 7.2 del plan de
 * seguridad/rendimiento).
 *
 * Criterio: minúsculas, sin acentos (á→a, ñ→n, ü→u), cualquier carácter no
 * alfanumérico → guion, guiones repetidos colapsados y recortados.
 *
 * La transliteración NO depende de iconv/intl: se usa un mapa explícito de
 * caracteres latinos. Motivo: `iconv('UTF-8','ASCII//TRANSLIT')` no translitera
 * de forma fiable en todas las plataformas (en el build de Windows/XAMPP
 * convierte `ñ`/`é` en un separador → `Español` → `espa-nol`). El mapa manual es
 * determinista y portable (Windows, Debian/Docker…).
 *
 * Sin dependencias externas (no se añade cocur/slugify).
 */

class Slug {
    /**
     * Mapa de transliteración (entrada ya en minúsculas) → ASCII.
     * Cubre Latin-1 y Latin Extended-A habituales en títulos occidentales.
     */
    private const TRANSLIT = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e',
        'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ğ' => 'g', 'ģ' => 'g',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'ı' => 'i',
        'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l', 'ļ' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'ō' => 'o', 'ő' => 'o',
        'ř' => 'r', 'ŕ' => 'r',
        'ś' => 's', 'š' => 's', 'ş' => 's',
        'ť' => 't', 'ţ' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u',
        'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'þ' => 'th',
    ];

    /**
     * Convierte un texto libre en un slug seguro para URL.
     *
     * @param string $texto    Texto origen (título, nombre…).
     * @param string $fallback Slug a usar si el resultado queda vacío.
     */
    public static function make(string $texto, string $fallback = 'juego'): string {
        $s = trim($texto);

        // 1) Minúsculas respetando UTF-8 (mbstring; el proyecto ya lo usa).
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        // 2) Transliteración determinista de caracteres latinos.
        $s = strtr($s, self::TRANSLIT);

        // 3) Resto de no-ASCII (si quedara algo) vía iconv como red de seguridad.
        if (preg_match('/[^\x00-\x7F]/', $s)) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($conv !== false) {
                $s = $conv;
            }
        }

        // 4) Normaliza a [a-z0-9-], colapsa y recorta.
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');

        return $s === '' ? $fallback : $s;
    }

    /**
     * Devuelve un slug único: $base o $base-2, $base-3… hasta que $exists() sea
     * falso para el candidato.
     *
     * @param string   $base     Texto origen (se slugifica con make()).
     * @param callable $exists   fn(string $slug): bool — true si ya está en uso.
     * @param string   $fallback Slug base si el texto queda vacío.
     */
    public static function unique(string $base, callable $exists, string $fallback = 'juego'): string {
        $slug = self::make($base, $fallback);
        if (!$exists($slug)) {
            return $slug;
        }

        $i = 2;
        while ($exists($slug . '-' . $i)) {
            $i++;
            // Salvaguarda: si por lo que sea se agotan los sufijos, usa un
            // sufijo aleatorio (evita un bucle potencialmente infinito).
            if ($i > 10000) {
                return $slug . '-' . bin2hex(random_bytes(3));
            }
        }

        return $slug . '-' . $i;
    }
}
