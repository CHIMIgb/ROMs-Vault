<?php
/**
 * config/IdiomasNormalizer.php
 * Normaliza el campo `juegos.idiomas` (texto libre en español con erratas) a
 * una lista canónica separada por comas.
 *
 * Se usa en dos momentos:
 *   1. Limpieza de los datos YA registrados (scripts/normalizar-idiomas.php).
 *   2. Alimentar la traducción con un texto fiable: si se envía el valor crudo
 *      al proveedor, erratas como "Frances" se traducen como "France" (el país)
 *      en vez de "French".
 *
 * Reglas:
 *   - Separa por coma, punto o punto y coma (igual que la vista de detalle).
 *   - Normaliza el token (minúsculas + sin acentos) y lo busca en MAP.
 *   - Variantes reales de la BD: Ingles/Inglés, Frances/Francés, Aleman/Alemán,
 *     italiano/Italiano, Japones/Japonese/Japoneses.
 *   - Sufijo "(parche)" se conserva: "Español (parche)".
 *   - Basura ("NULL", "11", vacíos) se descarta.
 *   - Token desconocido se conserva con la inicial en mayúscula (no se inventa).
 *   - Deduplica sin importar mayúsculas/acentos, preservando el orden original.
 *
 * No depende de iconv ni del locale: usa un mapa explícito y determinista.
 */
class IdiomasNormalizer {

    /** Variante normalizada (minúsculas, sin acentos) => nombre canónico en español. */
    private const MAP = [
        'espanol'   => 'Español',
        'ingles'    => 'Inglés',
        'frances'   => 'Francés',
        'aleman'    => 'Alemán',
        'italiano'  => 'Italiano',
        'japones'   => 'Japonés',
        'japonese'  => 'Japonés',
        'japoneses' => 'Japonés',
        'ruso'      => 'Ruso',
        'holandes'  => 'Holandés',
        'sueco'     => 'Sueco',
        'danes'     => 'Danés',
        'finlandes' => 'Finlandés',
        'noruego'   => 'Noruego',
    ];

    /** Tokens sin valor que se descartan (comparados ya normalizados). */
    private const JUNK = ['null', 'nulo', 'nan', 'undefined', 'n/a', '-'];

    /**
     * Normaliza el campo a una lista canónica separada por ", ".
     * Devuelve null si no queda ningún idioma válido.
     */
    public static function normalizar(?string $raw): ?string {
        $tokens = self::tokens($raw);
        return $tokens === [] ? null : implode(', ', $tokens);
    }

    /**
     * Devuelve la lista de nombres canónicos (en español), sin duplicados y
     * preservando el orden de aparición.
     *
     * @return array<int,string>
     */
    public static function tokens(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $salida  = [];
        $vistos  = [];

        foreach (preg_split('/[,.;]/u', $raw) ?: [] as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            // Sufijo "(parche)" (decisión Opción B): se conserva la marca.
            $parche = false;
            if (preg_match('/\(\s*parche\s*\)/iu', $token, $m)) {
                $parche = true;
                $token  = trim(str_replace($m[0], '', $token));
            }
            if ($token === '') {
                continue;
            }

            $clave = self::clave($token);
            if ($clave === '' || in_array($clave, self::JUNK, true) || ctype_digit($clave)) {
                continue;
            }

            $nombre = self::MAP[$clave] ?? self::ucfirstSeguro($token);
            if ($parche) {
                $nombre .= ' (parche)';
            }

            $dedupe = mb_strtolower($nombre, 'UTF-8');
            if (isset($vistos[$dedupe])) {
                continue;
            }
            $vistos[$dedupe] = true;
            $salida[] = $nombre;
        }

        return $salida;
    }

    /** Clave de comparación: minúsculas y sin acentos. */
    private static function clave(string $token): string {
        return trim(self::sinAcentos(mb_strtolower($token, 'UTF-8')));
    }

    /** Mapa explícito de diacríticos (determinista, sin depender de iconv). */
    private static function sinAcentos(string $texto): string {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    /** Primera letra en mayúscula respetando UTF-8. */
    private static function ucfirstSeguro(string $texto): string {
        if ($texto === '') {
            return $texto;
        }
        return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($texto, 1, null, 'UTF-8');
    }
}
