<?php
/**
 * tests/Unit/TraduccionServiceCamposTest.php
 * Unit tests de TraduccionService::traducirCampos().
 *
 * Desde la corrección de calidad (2026-10-13) el campo `idiomas` es un
 * vocabulario CERRADO y se resuelve con la tabla determinista
 * `IdiomasTranslations`; NO se envía al proveedor (que alucina/duplica/reordena
 * con nombres cortos). El resto de campos (p. ej. `descripcion`) sí van al
 * proveedor, y si no queda nada que enviar, no se llama.
 *
 * Se usa un proveedor falso (sin red): no se toca LibreTranslate ni la BD.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TraduccionService;
use TranslationProvider;

/** Proveedor de prueba que marca cada texto con el idioma destino. */
final class ProveedorFalsoTraduccion implements TranslationProvider {

    /** @var array<string,string> Último lote enviado (para inspeccionarlo en el test). */
    public array $enviado = [];

    /** Número de veces que se invocó translateBatch. */
    public int $llamadas = 0;

    public function name(): string {
        return 'fake';
    }

    public function isConfigured(): bool {
        return true;
    }

    public function translateBatch(array $textos, string $source, string $target): array {
        $this->llamadas++;
        $this->enviado = $textos;
        $salida = [];
        foreach ($textos as $clave => $valor) {
            $salida[$clave] = is_string($valor) ? '[' . $target . ']' . $valor : $valor;
        }
        return $salida;
    }
}

class TraduccionServiceCamposTest extends TestCase {

    public function testIdiomasNoSeEnviaAlProveedorYSeResuelveConLaTabla(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'descripcion' => 'Sinopsis del juego',
            'idiomas'     => 'Español, Inglés, Francés',
        ], 'en');

        // Solo la descripción viaja al proveedor; `idiomas` se resuelve en local.
        $this->assertSame(['descripcion'], array_keys($prov->enviado));
        $this->assertSame('[en]Sinopsis del juego', $salida['descripcion']);
        $this->assertSame('Spanish, English, French', $salida['idiomas']);
    }

    public function testSoloIdiomasNoLlamaAlProveedor(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Inglés',
        ], 'tr');

        $this->assertSame(0, $prov->llamadas, 'No debe llamar al proveedor si solo hay idiomas.');
        $this->assertSame('İngilizce', $salida['idiomas']);
    }

    /**
     * El bug original: un token único (44 juegos con "Inglés") el proveedor lo
     * alucinaba (fr "Changements climatiques", tr "İngilizce İngilizce...",
     * ja "日本語 English", zh "联合国"). La tabla lo resuelve de forma exacta.
     */
    public function testTokenUnicoSeTraduceDeFormaExacta(): void {
        $prov = new ProveedorFalsoTraduccion();

        $esperado = [
            'en' => 'English',
            'fr' => 'Anglais',
            'de' => 'Englisch',
            'it' => 'Inglese',
            'pt' => 'Inglês',
            'ru' => 'Английский',
            'tr' => 'İngilizce',
            'ja' => '英語',
            'zh' => '英语',
            'ko' => '영어',
            'ar' => 'الإنجليزية',
        ];
        foreach ($esperado as $target => $texto) {
            $salida = TraduccionService::traducirCampos($prov, ['idiomas' => 'Inglés'], $target);
            $this->assertSame($texto, $salida['idiomas'], "Fallo traduciendo 'Inglés' a {$target}");
        }
        $this->assertSame(0, $prov->llamadas);
    }

    public function testPreservaOrdenYDeduplica(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Español, Inglés, Francés, Inglés',
        ], 'de');

        // Orden del origen preservado y sin duplicados.
        $this->assertSame('Spanisch, Englisch, Französisch', $salida['idiomas']);
    }

    public function testParcheSeLocaliza(): void {
        $prov = new ProveedorFalsoTraduccion();

        $this->assertSame(
            'Spanish (patch)',
            TraduccionService::traducirCampos($prov, ['idiomas' => 'Español (parche)'], 'en')['idiomas']
        );
        $this->assertSame(
            'スペイン語 (パッチ)',
            TraduccionService::traducirCampos($prov, ['idiomas' => 'Español (parche)'], 'ja')['idiomas']
        );
        $this->assertSame(
            'Испанский (патч)',
            TraduccionService::traducirCampos($prov, ['idiomas' => 'Español (parche)'], 'ru')['idiomas']
        );
    }

    public function testDescartaTokensBasura(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'NULL, Inglés, 11',
        ], 'en');

        // IdiomasNormalizer descarta "NULL" y "11": solo queda un idioma real.
        $this->assertSame('English', $salida['idiomas']);
        $this->assertSame(0, $prov->llamadas);
    }

    public function testTokenDesconocidoSeConservaSinInventar(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Inglés, Klingon',
        ], 'fr');

        $this->assertSame('Anglais, Klingon', $salida['idiomas']);
    }

    public function testIdiomaEspanolDevuelveElOrigenNormalizado(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Inglés, Español, Francés',
        ], 'es');

        $this->assertSame('Inglés, Español, Francés', $salida['idiomas']);
        $this->assertSame(0, $prov->llamadas);
    }

    public function testSinIdiomasNoAnadeLaClave(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'descripcion' => 'Solo sinopsis',
        ], 'de');

        $this->assertSame(['descripcion'], array_keys($prov->enviado));
        $this->assertSame('[de]Solo sinopsis', $salida['descripcion']);
        $this->assertArrayNotHasKey('idiomas', $salida);
    }
}
