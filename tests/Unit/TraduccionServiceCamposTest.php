<?php
/**
 * tests/Unit/TraduccionServiceCamposTest.php
 * Unit tests de TraduccionService::traducirCampos(): el campo `idiomas` debe
 * enviarse como UNA sola cadena con los idiomas unidos por " / " (el proveedor
 * traduce con contexto y conserva el delimitador) y, al volver, re-separarse por
 * "/" y re-unirse con ", " para que las vistas pinten chips separados.
 *
 * Se usa un proveedor falso (sin red): no se toca LibreTranslate ni la BD.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TraduccionService;
use TranslationProvider;

/**
 * Proveedor de prueba que imita a LibreTranslate: marca cada texto con el idioma
 * destino y, si el valor trae el delimitador " / ", traduce cada parte por
 * separado conservando el delimitador (como hace el servicio real).
 */
final class ProveedorFalsoTraduccion implements TranslationProvider {

    /** @var array<string,string> Último lote enviado (para inspeccionarlo en el test). */
    public array $enviado = [];

    public function name(): string {
        return 'fake';
    }

    public function isConfigured(): bool {
        return true;
    }

    public function translateBatch(array $textos, string $source, string $target): array {
        $this->enviado = $textos;
        $salida = [];
        foreach ($textos as $clave => $valor) {
            if (!is_string($valor) || trim($valor) === '') {
                $salida[$clave] = $valor;
                continue;
            }
            if (str_contains($valor, '/')) {
                $partes = array_map(
                    static fn(string $p): string => '[' . $target . ']' . trim($p),
                    explode('/', $valor)
                );
                $salida[$clave] = implode(' / ', $partes);
            } else {
                $salida[$clave] = '[' . $target . ']' . $valor;
            }
        }
        return $salida;
    }
}

/** Proveedor que devuelve el separador ideográfico japonés/chino (、). */
final class ProveedorFalsoIdeografico implements TranslationProvider {

    public function name(): string {
        return 'fake-ideografico';
    }

    public function isConfigured(): bool {
        return true;
    }

    public function translateBatch(array $textos, string $source, string $target): array {
        $salida = [];
        foreach ($textos as $clave => $valor) {
            $salida[$clave] = is_string($valor) && $clave === 'idiomas'
                ? 'スペイン語、英語、フランス語'
                : $valor;
        }
        return $salida;
    }
}

class TraduccionServiceCamposTest extends TestCase {

    public function testIdiomasSeEnviaEnUnaCadenaConDelimitadorYSeResepara(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'descripcion' => 'Sinopsis del juego',
            'idiomas'     => 'Español, Inglés, Francés',
        ], 'en');

        // El proveedor recibe la descripción y UNA sola cadena de idiomas con " / ".
        $this->assertSame(['descripcion', 'idiomas'], array_keys($prov->enviado));
        $this->assertSame('Español / Inglés / Francés', $prov->enviado['idiomas']);

        // La salida re-separa por "/" y re-une con ", " (chips separados).
        $this->assertSame('[en]Español, [en]Inglés, [en]Francés', $salida['idiomas']);
        $this->assertSame('[en]Sinopsis del juego', $salida['descripcion']);
    }

    public function testSinIdiomasNoEnviaLaClave(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'descripcion' => 'Solo sinopsis',
        ], 'de');

        $this->assertSame(['descripcion'], array_keys($prov->enviado));
        $this->assertSame('[de]Solo sinopsis', $salida['descripcion']);
        $this->assertArrayNotHasKey('idiomas', $salida);
    }

    public function testDescartaTokensBasuraAntesDeEnviar(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'NULL, Inglés, 11',
        ], 'en');

        // IdiomasNormalizer descarta "NULL" y "11": solo queda un idioma real.
        $this->assertSame(['idiomas'], array_keys($prov->enviado));
        $this->assertSame('Inglés', $prov->enviado['idiomas']);
        $this->assertSame('[en]Inglés', $salida['idiomas']);
    }

    public function testPreservaSufijoParcheEnUnToken(): void {
        $prov = new ProveedorFalsoTraduccion();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Español (parche), Inglés',
        ], 'en');

        $this->assertSame('Español (parche) / Inglés', $prov->enviado['idiomas']);
        $this->assertSame('[en]Español (parche), [en]Inglés', $salida['idiomas']);
    }

    public function testSeparadorIdeograficoSeNormalizaAComas(): void {
        $prov = new ProveedorFalsoIdeografico();

        $salida = TraduccionService::traducirCampos($prov, [
            'idiomas' => 'Español, Inglés, Francés',
        ], 'ja');

        // Aunque el proveedor devuelva "、", la salida usa ", " (chips separados).
        $this->assertSame('スペイン語, 英語, フランス語', $salida['idiomas']);
    }
}
