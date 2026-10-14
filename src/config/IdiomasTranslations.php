<?php
require_once __DIR__ . '/IdiomasNormalizer.php';
require_once __DIR__ . '/i18n.php';

/**
 * config/IdiomasTranslations.php
 * Traduce el campo `idiomas` (lista de idiomas disponibles de un juego) a los
 * idiomas de la UI mediante una TABLA DETERMINISTA.
 *
 * ¿Por qué no el proveedor de traducción? `idiomas` es un vocabulario CERRADO
 * (12 nombres canónicos) y el proveedor ML demostró ser inadecuado para nombres
 * cortos:
 *   - Token aislado: alucina (es→fr "Inglés" = "Changements climatiques",
 *     es→tr = "İngilizce İngilizce İngilizce English", es→ja = "日本語 English").
 *   - Listas cortas: duplica ("Almanca, Almanca") y a veces reordena o pierde
 *     el primer ítem (la lista de 12 en→ 11 elementos).
 *
 * Esta tabla es determinista, offline, preserva el orden del origen, deduplica
 * y nunca inventa: un token desconocido se conserva tal cual (en español).
 *
 * El vocabulario de origen es el que produce IdiomasNormalizer::tokens(): los
 * nombres canónicos en español (Español, Inglés, Francés, Alemán, Italiano,
 * Japonés, Ruso, Holandés, Sueco, Danés, Finlandés, Noruego) y el sufijo
 * "(parche)".
 */
class IdiomasTranslations {

    /**
     * Nombre canónico en español => nombre localizado por idioma de la UI.
     * Estilo de etiqueta (inicial en mayúscula) para que los chips sean
     * homogéneos en todos los idiomas.
     */
    private const MAP = [
        'Español' => [
            'en' => 'Spanish', 'fr' => 'Espagnol', 'de' => 'Spanisch', 'it' => 'Spagnolo',
            'pt' => 'Espanhol', 'ru' => 'Испанский', 'tr' => 'İspanyolca', 'ja' => 'スペイン語',
            'zh' => '西班牙语', 'ko' => '스페인어', 'ar' => 'الإسبانية',
        ],
        'Inglés' => [
            'en' => 'English', 'fr' => 'Anglais', 'de' => 'Englisch', 'it' => 'Inglese',
            'pt' => 'Inglês', 'ru' => 'Английский', 'tr' => 'İngilizce', 'ja' => '英語',
            'zh' => '英语', 'ko' => '영어', 'ar' => 'الإنجليزية',
        ],
        'Francés' => [
            'en' => 'French', 'fr' => 'Français', 'de' => 'Französisch', 'it' => 'Francese',
            'pt' => 'Francês', 'ru' => 'Французский', 'tr' => 'Fransızca', 'ja' => 'フランス語',
            'zh' => '法语', 'ko' => '프랑스어', 'ar' => 'الفرنسية',
        ],
        'Alemán' => [
            'en' => 'German', 'fr' => 'Allemand', 'de' => 'Deutsch', 'it' => 'Tedesco',
            'pt' => 'Alemão', 'ru' => 'Немецкий', 'tr' => 'Almanca', 'ja' => 'ドイツ語',
            'zh' => '德语', 'ko' => '독일어', 'ar' => 'الألمانية',
        ],
        'Italiano' => [
            'en' => 'Italian', 'fr' => 'Italien', 'de' => 'Italienisch', 'it' => 'Italiano',
            'pt' => 'Italiano', 'ru' => 'Итальянский', 'tr' => 'İtalyanca', 'ja' => 'イタリア語',
            'zh' => '意大利语', 'ko' => '이탈리아어', 'ar' => 'الإيطالية',
        ],
        'Japonés' => [
            'en' => 'Japanese', 'fr' => 'Japonais', 'de' => 'Japanisch', 'it' => 'Giapponese',
            'pt' => 'Japonês', 'ru' => 'Японский', 'tr' => 'Japonca', 'ja' => '日本語',
            'zh' => '日语', 'ko' => '일본어', 'ar' => 'اليابانية',
        ],
        'Ruso' => [
            'en' => 'Russian', 'fr' => 'Russe', 'de' => 'Russisch', 'it' => 'Russo',
            'pt' => 'Russo', 'ru' => 'Русский', 'tr' => 'Rusça', 'ja' => 'ロシア語',
            'zh' => '俄语', 'ko' => '러시아어', 'ar' => 'الروسية',
        ],
        'Holandés' => [
            'en' => 'Dutch', 'fr' => 'Néerlandais', 'de' => 'Niederländisch', 'it' => 'Olandese',
            'pt' => 'Holandês', 'ru' => 'Нидерландский', 'tr' => 'Felemenkçe', 'ja' => 'オランダ語',
            'zh' => '荷兰语', 'ko' => '네덜란드어', 'ar' => 'الهولندية',
        ],
        'Sueco' => [
            'en' => 'Swedish', 'fr' => 'Suédois', 'de' => 'Schwedisch', 'it' => 'Svedese',
            'pt' => 'Sueco', 'ru' => 'Шведский', 'tr' => 'İsveççe', 'ja' => 'スウェーデン語',
            'zh' => '瑞典语', 'ko' => '스웨덴어', 'ar' => 'السويدية',
        ],
        'Danés' => [
            'en' => 'Danish', 'fr' => 'Danois', 'de' => 'Dänisch', 'it' => 'Danese',
            'pt' => 'Dinamarquês', 'ru' => 'Датский', 'tr' => 'Danca', 'ja' => 'デンマーク語',
            'zh' => '丹麦语', 'ko' => '덴마크어', 'ar' => 'الدنماركية',
        ],
        'Finlandés' => [
            'en' => 'Finnish', 'fr' => 'Finnois', 'de' => 'Finnisch', 'it' => 'Finlandese',
            'pt' => 'Finlandês', 'ru' => 'Финский', 'tr' => 'Fince', 'ja' => 'フィンランド語',
            'zh' => '芬兰语', 'ko' => '핀란드어', 'ar' => 'الفنلندية',
        ],
        'Noruego' => [
            'en' => 'Norwegian', 'fr' => 'Norvégien', 'de' => 'Norwegisch', 'it' => 'Norvegese',
            'pt' => 'Norueguês', 'ru' => 'Норвежский', 'tr' => 'Norveççe', 'ja' => 'ノルウェー語',
            'zh' => '挪威语', 'ko' => '노르웨이어', 'ar' => 'النرويجية',
        ],
    ];

    /** Palabra localizada del sufijo "(parche)". */
    private const PARCHE = [
        'en' => 'patch', 'fr' => 'patch', 'de' => 'Patch', 'it' => 'patch',
        'pt' => 'patch', 'ru' => 'патч', 'tr' => 'yama', 'ja' => 'パッチ',
        'zh' => '补丁', 'ko' => '패치', 'ar' => 'رقعة',
    ];

    /**
     * Traduce una lista de idiomas en español (valor de `juegos.idiomas`) al
     * idioma destino, preservando el orden original y sin duplicados.
     *
     * @param string|null $origenEs Valor de `idiomas` en español (se normaliza).
     * @param string      $target   Código de idioma destino (whitelist I18n).
     * @return string|null Lista traducida separada por ", " o null si no hay idiomas.
     */
    public static function traducir(?string $origenEs, string $target): ?string {
        $tokens = IdiomasNormalizer::tokens($origenEs);
        if ($tokens === []) {
            return null;
        }

        // Idioma no soportado -> se devuelve el origen ya normalizado (en español).
        if (!in_array($target, I18n::LANGUAGES, true) || $target === I18n::DEFAULT_LANG) {
            return implode(', ', $tokens);
        }

        $salida = [];
        foreach ($tokens as $token) {
            $parche = false;
            // IdiomasNormalizer conserva el sufijo "(parche)" en el token.
            if (preg_match('/^(.*?)\s*\(parche\)$/u', $token, $m)) {
                $parche = true;
                $token  = trim($m[1]);
            }

            // Vocabulario cerrado: token conocido -> traducido; desconocido -> tal cual.
            $nombre = self::MAP[$token][$target] ?? $token;

            if ($parche) {
                $nombre .= ' (' . (self::PARCHE[$target] ?? 'parche') . ')';
            }
            $salida[] = $nombre;
        }

        return $salida === [] ? null : implode(', ', $salida);
    }
}
