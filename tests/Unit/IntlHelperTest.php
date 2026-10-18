<?php
/**
 * tests/Unit/IntlHelperTest.php
 * Unit tests de IntlHelper::formatBytes() — redondeo adaptativo MB/GB
 * (docs/2026-10-17-tarjeta-juego-tamano-y-analisis.md).
 *
 * Contrato verificado:
 *   - B/KB/MB enteros y GB con 1 decimal, con separadores según el idioma activo.
 *   - El redondeo nunca alcanza la unidad superior: 1073741823 B (1 byte menos
 *     que 1 GB) se muestra como "1,0 GB" y no como "1024 MB".
 *   - Entradas inválidas (null, '', texto no numérico) → "0 B".
 *   - Valores reales del catálogo (los 122 juegos de import_data.sql).
 *
 * Debe dar el mismo texto con y sin la extensión intl (el helper tiene fallback).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use I18n;
use IntlHelper;

class IntlHelperTest extends TestCase {

    /** @var string Idioma activo antes de cada test (se restaura siempre). */
    private string $langPrevio = '';

    protected function setUp(): void {
        $this->langPrevio = I18n::getLang();
    }

    protected function tearDown(): void {
        I18n::setLang($this->langPrevio);
    }

    public function testEntradasInvalidasDevuelvenCeroBytes(): void {
        $this->assertSame('0 B', IntlHelper::formatBytes(null));
        $this->assertSame('0 B', IntlHelper::formatBytes(''));
        $this->assertSame('0 B', IntlHelper::formatBytes('abc'));
        $this->assertSame('0 B', IntlHelper::formatBytes(0));
    }

    public function testBytesSinDecimalesConSeparadorDeMiles(): void {
        I18n::setLang('es');
        $this->assertSame('512 B', IntlHelper::formatBytes(512));
        $this->assertSame('1.023 B', IntlHelper::formatBytes(1023));

        I18n::setLang('en');
        $this->assertSame('512 B', IntlHelper::formatBytes(512));
        $this->assertSame('1,023 B', IntlHelper::formatBytes(1023));
    }

    public function testKilobytesRedondeadosAEntero(): void {
        I18n::setLang('es');
        $this->assertSame('1 KB', IntlHelper::formatBytes(1024));
        $this->assertSame('512 KB', IntlHelper::formatBytes(524288));
        // 31457 B = 30,72 KB → 31 KB (redondeo, sin decimales)
        $this->assertSame('31 KB', IntlHelper::formatBytes(31457));
    }

    public function testMegabytesRedondeadosAEntero(): void {
        I18n::setLang('es');
        $this->assertSame('1 MB', IntlHelper::formatBytes(1048576));
        // 456854077 B = 435,69 MB → 436 MB (juego real del catálogo)
        $this->assertSame('436 MB', IntlHelper::formatBytes(456854077));
        // 1014004449 B = 967,03 MB → 967 MB (juego real del catálogo)
        $this->assertSame('967 MB', IntlHelper::formatBytes(1014004449));
        $this->assertSame('1 MB', IntlHelper::formatBytes('1048576'));
    }

    public function testGigabytesConUnDecimal(): void {
        I18n::setLang('es');
        $this->assertSame('1,0 GB', IntlHelper::formatBytes(1073741824));
        // 1768360509 B = 1,647 GB → 1,6 GB (juego real del catálogo)
        $this->assertSame('1,6 GB', IntlHelper::formatBytes(1768360509));
        // 1243548221 B = 1,158 GB → 1,2 GB (juego real del catálogo)
        $this->assertSame('1,2 GB', IntlHelper::formatBytes(1243548221));

        I18n::setLang('en');
        $this->assertSame('1.0 GB', IntlHelper::formatBytes(1073741824));
        $this->assertSame('1.6 GB', IntlHelper::formatBytes(1768360509));
    }

    public function testElRedondeoNuncaAlcanzaLaUnidadSuperior(): void {
        I18n::setLang('es');
        // 1073741823 B = 1023,999 MB: sin promoción se vería "1024 MB".
        $this->assertSame('1,0 GB', IntlHelper::formatBytes(1073741823));
        // 1048575 B = 1023,999 KB: sin promoción se vería "1024 KB".
        $this->assertSame('1 MB', IntlHelper::formatBytes(1048575));

        I18n::setLang('en');
        $this->assertSame('1.0 GB', IntlHelper::formatBytes(1073741823));
        $this->assertSame('1 MB', IntlHelper::formatBytes(1048575));
    }

    public function testAmbasUnidadesConvivenEnElCatalogo(): void {
        I18n::setLang('es');
        // Mismo catálogo → MB para ROMs pequeñas, GB para las grandes.
        $this->assertStringEndsWith('MB', IntlHelper::formatBytes(330154639));   // 314,86 MB
        $this->assertStringEndsWith('GB', IntlHelper::formatBytes(1737888890));  // 1,619 GB
        $this->assertSame('315 MB', IntlHelper::formatBytes(330154639));
        $this->assertSame('1,6 GB', IntlHelper::formatBytes(1737888890));
    }
}
