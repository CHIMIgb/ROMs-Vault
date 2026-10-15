<?php
/**
 * tests/Unit/IdiomasNormalizerTest.php
 * Unit tests de la normalización de juegos.idiomas (i18n, bloque 2).
 *
 * Verifica el contrato de IdiomasNormalizer::normalizar()/tokens():
 *   - variantes reales de la BD (Ingles/Inglés, Frances/Francés, Aleman/Alemán)
 *   - variantes de japonés (Japones/Japonese/Japoneses)
 *   - separadores mezclados (coma, punto, punto y coma)
 *   - descarte de basura ("NULL", "11", vacíos)
 *   - sufijo "(parche)" preservado
 *   - token desconocido conservado con inicial mayúscula
 *   - dedupe sin importar mayúsculas/acentos, preservando el orden
 *   - idempotencia
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use IdiomasNormalizer;

class IdiomasNormalizerTest extends TestCase {

    public function testNormalizaVariantesReales(): void {
        $this->assertSame(
            'Inglés, Francés, Alemán, Italiano, Español',
            IdiomasNormalizer::normalizar('Ingles, Frances, Aleman, Italiano, Español')
        );
        $this->assertSame(
            'Español, Inglés, Francés',
            IdiomasNormalizer::normalizar('Español, Inglés, Francés')
        );
    }

    public function testVariantesDeJapones(): void {
        $this->assertSame('Japonés', IdiomasNormalizer::normalizar('Japones'));
        $this->assertSame('Japonés', IdiomasNormalizer::normalizar('Japonese'));
        $this->assertSame('Japonés', IdiomasNormalizer::normalizar('Japoneses'));
        $this->assertSame('Japonés', IdiomasNormalizer::normalizar('japonés'));
    }

    public function testSeparadoresMezclados(): void {
        $this->assertSame(
            'Español, Inglés, Francés, Italiano',
            IdiomasNormalizer::normalizar('Español. Inglés; Francés, Italiano')
        );
    }

    public function testDescartaBasura(): void {
        $this->assertSame('Inglés', IdiomasNormalizer::normalizar('NULL, 11, Inglés'));
        $this->assertNull(IdiomasNormalizer::normalizar('NULL'));
        $this->assertNull(IdiomasNormalizer::normalizar('11'));
        $this->assertNull(IdiomasNormalizer::normalizar(''));
        $this->assertNull(IdiomasNormalizer::normalizar('   '));
        $this->assertNull(IdiomasNormalizer::normalizar(null));
    }

    public function testPreservaSufijoParche(): void {
        $this->assertSame(
            'Español (parche), Inglés',
            IdiomasNormalizer::normalizar('Español (parche), Inglés')
        );
        $this->assertSame('Español (parche)', IdiomasNormalizer::normalizar('español (PARCHE)'));
    }

    public function testTokenDesconocidoConservaMayusculaInicial(): void {
        $this->assertSame('Klingon, Inglés', IdiomasNormalizer::normalizar('klingon, Inglés'));
    }

    public function testDeduplicaPreservandoOrden(): void {
        $this->assertSame('Inglés, Español', IdiomasNormalizer::normalizar('Inglés, Español, Ingles'));
        $this->assertSame('Inglés', IdiomasNormalizer::normalizar('INGLÉS, ingles, Ingles'));
    }

    public function testTokensDevuelveArray(): void {
        $this->assertSame(
            ['Español', 'Inglés', 'Francés'],
            IdiomasNormalizer::tokens('Español, Inglés, Frances')
        );
        $this->assertSame([], IdiomasNormalizer::tokens('NULL, 11'));
    }

    public function testEsIdempotente(): void {
        $una  = IdiomasNormalizer::normalizar('Ingles, Frances ,  Japones, NULL ');
        $dos  = IdiomasNormalizer::normalizar($una);
        $this->assertSame('Inglés, Francés, Japonés', $una);
        $this->assertSame($una, $dos);
    }
}
