<?php
/**
 * tests/Unit/IdiomasTranslationsTest.php
 * Tabla determinista de idiomas (vocabulario cerrado): 12 nombres canónicos en
 * los 11 idiomas destino. Verifica traducción exacta, orden, deduplicación,
 * sufijo "(parche)", tokens desconocidos y casos límite. Sin red ni BD.
 */

namespace Tests\Unit;

use IdiomasTranslations;
use PHPUnit\Framework\TestCase;

class IdiomasTranslationsTest extends TestCase {

    private const ORIGEN = 'Español, Inglés, Francés, Alemán, Italiano, Japonés, '
        . 'Ruso, Holandés, Sueco, Danés, Finlandés, Noruego';

    /**
     * Esperado por idioma para la lista completa (en el orden del origen).
     * @return array<string,array{string,int}> texto y número de nombres.
     */
    private static function esperados(): array {
        return [
            'en' => 'Spanish, English, French, German, Italian, Japanese, Russian, Dutch, Swedish, Danish, Finnish, Norwegian',
            'fr' => 'Espagnol, Anglais, Français, Allemand, Italien, Japonais, Russe, Néerlandais, Suédois, Danois, Finnois, Norvégien',
            'de' => 'Spanisch, Englisch, Französisch, Deutsch, Italienisch, Japanisch, Russisch, Niederländisch, Schwedisch, Dänisch, Finnisch, Norwegisch',
            'it' => 'Spagnolo, Inglese, Francese, Tedesco, Italiano, Giapponese, Russo, Olandese, Svedese, Danese, Finlandese, Norvegese',
            'pt' => 'Espanhol, Inglês, Francês, Alemão, Italiano, Japonês, Russo, Holandês, Sueco, Dinamarquês, Finlandês, Norueguês',
            'ru' => 'Испанский, Английский, Французский, Немецкий, Итальянский, Японский, Русский, Нидерландский, Шведский, Датский, Финский, Норвежский',
            'tr' => 'İspanyolca, İngilizce, Fransızca, Almanca, İtalyanca, Japonca, Rusça, Felemenkçe, İsveççe, Danca, Fince, Norveççe',
            'ja' => 'スペイン語, 英語, フランス語, ドイツ語, イタリア語, 日本語, ロシア語, オランダ語, スウェーデン語, デンマーク語, フィンランド語, ノルウェー語',
            'zh' => '西班牙语, 英语, 法语, 德语, 意大利语, 日语, 俄语, 荷兰语, 瑞典语, 丹麦语, 芬兰语, 挪威语',
            'ko' => '스페인어, 영어, 프랑스어, 독일어, 이탈리아어, 일본어, 러시아어, 네덜란드어, 스웨덴어, 덴마크어, 핀란드어, 노르웨이어',
            'ar' => 'الإسبانية, الإنجليزية, الفرنسية, الألمانية, الإيطالية, اليابانية, الروسية, الهولندية, السويدية, الدنماركية, الفنلندية, النرويجية',
        ];
    }

    public function testTraduceLaListaCompletaEnLosOnceIdiomas(): void {
        foreach (self::esperados() as $target => $esperado) {
            $this->assertSame(
                $esperado,
                IdiomasTranslations::traducir(self::ORIGEN, $target),
                "Lista completa incorrecta para '{$target}'"
            );
        }
    }

    public function testListaCompletaConservaElOrdenYDoceNombres(): void {
        foreach (self::esperados() as $target => $esperado) {
            $partes = explode(', ', (string) $esperado);
            $this->assertCount(12, $partes, "Se esperaban 12 nombres en '{$target}'");
            $this->assertSame(
                $partes,
                explode(', ', (string) IdiomasTranslations::traducir(self::ORIGEN, $target)),
                "El orden de los nombres no se conserva en '{$target}'"
            );
        }
    }

    public function testTokenUnicoSeTraduceDeFormaExacta(): void {
        $this->assertSame('English', IdiomasTranslations::traducir('Inglés', 'en'));
        $this->assertSame('Anglais', IdiomasTranslations::traducir('Inglés', 'fr'));
        $this->assertSame('İngilizce', IdiomasTranslations::traducir('Inglés', 'tr'));
        $this->assertSame('英語', IdiomasTranslations::traducir('Inglés', 'ja'));
        $this->assertSame('英语', IdiomasTranslations::traducir('Inglés', 'zh'));
        $this->assertSame('영어', IdiomasTranslations::traducir('Inglés', 'ko'));
        $this->assertSame('الإنجليزية', IdiomasTranslations::traducir('Inglés', 'ar'));
    }

    public function testDeduplicaPreservandoElOrden(): void {
        $this->assertSame(
            'Spanish, English, French',
            IdiomasTranslations::traducir('Español, Inglés, Francés, Inglés', 'en')
        );
    }

    public function testDescartaTokensBasura(): void {
        $this->assertSame('English', IdiomasTranslations::traducir('NULL, Inglés, 11', 'en'));
    }

    public function testTokenDesconocidoSeConservaSinInventar(): void {
        $this->assertSame('Anglais, Klingon', IdiomasTranslations::traducir('Inglés, Klingon', 'fr'));
    }

    public function testParcheSeLocaliza(): void {
        $this->assertSame('Spanish (patch)', IdiomasTranslations::traducir('Español (parche)', 'en'));
        $this->assertSame('Allemand (patch)', IdiomasTranslations::traducir('Alemán (parche)', 'fr'));
        $this->assertSame('Испанский (патч)', IdiomasTranslations::traducir('Español (parche)', 'ru'));
        $this->assertSame('スペイン語 (パッチ)', IdiomasTranslations::traducir('Español (parche)', 'ja'));
    }

    public function testEspanolDevuelveElOrigenNormalizado(): void {
        $this->assertSame(
            'Inglés, Español',
            IdiomasTranslations::traducir('inglés, español', 'es')
        );
    }

    public function testIdiomaNoSoportadoDevuelveElOrigenNormalizado(): void {
        $this->assertSame('Español, Inglés', IdiomasTranslations::traducir('Español, Inglés', 'xx'));
    }

    public function testVacioONullDevuelveNull(): void {
        $this->assertNull(IdiomasTranslations::traducir(null, 'en'));
        $this->assertNull(IdiomasTranslations::traducir('', 'en'));
        $this->assertNull(IdiomasTranslations::traducir('NULL, 11', 'en'));
    }
}
