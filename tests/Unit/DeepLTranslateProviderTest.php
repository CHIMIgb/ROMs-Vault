<?php
/**
 * tests/Unit/DeepLTranslateProviderTest.php
 * Unit tests del proveedor DeepL (sin red).
 *
 * Se comprueba el mapeo de códigos de idioma (MAYÚSCULAS y chino 'ZH-HANS'),
 * la guarda de configuración y el contrato de translateBatch() (vacíos tal
 * cual, reasignación por posición). La red se sustituye con un doble.
 */

namespace Tests\Unit;

use DeepLTranslateProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

require_once __DIR__ . '/../../src/config/translation/DeepLTranslateProvider.php';

/**
 * Doble de prueba sin red: captura lo que se iba a enviar a DeepL y marca cada
 * texto para comprobar la reasignación por posición.
 */
final class DeepLTranslateProviderDouble extends DeepLTranslateProvider {

    /** @var array<int,string> Textos que se habrían enviado. */
    public array $textosEnviados = [];

    public string $origen = '';
    public string $destino = '';

    public function __construct(string $apiKey = 'clave-ficticia') {
        parent::__construct($apiKey);
    }

    protected function request(array $textos, string $source, string $target): array {
        $this->textosEnviados = $textos;
        $this->origen         = $source;
        $this->destino        = $target;
        return array_map(static fn (string $t): string => 'X:' . $t, $textos);
    }
}

class DeepLTranslateProviderTest extends TestCase {

    public function testNameIsDeepl(): void {
        $this->assertSame('deepl', (new DeepLTranslateProvider('k'))->name());
    }

    public function testIsConfiguredReflectsApiKey(): void {
        $this->assertTrue((new DeepLTranslateProvider('clave-deepl'))->isConfigured());
        $this->assertFalse((new DeepLTranslateProvider(''))->isConfigured());
        $this->assertFalse((new DeepLTranslateProvider('   '))->isConfigured());
    }

    public function testEndpointsConstants(): void {
        $this->assertSame('https://api-free.deepl.com/v2/translate', DeepLTranslateProvider::DEFAULT_ENDPOINT);
        $this->assertSame('https://api.deepl.com/v2/translate', DeepLTranslateProvider::PRO_ENDPOINT);
    }

    public function testMapCodeUppercasesAndMapsChineseSimplified(): void {
        $provider = new DeepLTranslateProvider('k');
        $metodo = new \ReflectionMethod(DeepLTranslateProvider::class, 'mapCode');
        $metodo->setAccessible(true);

        $esperado = [
            'es' => 'ES',
            'en' => 'EN',
            'fr' => 'FR',
            'pt' => 'PT',
            'de' => 'DE',
            'it' => 'IT',
            'ru' => 'RU',
            'tr' => 'TR',
            'ja' => 'JA',
            'zh' => 'ZH-HANS',
            'ko' => 'KO',
            'ar' => 'AR',
        ];
        foreach ($esperado as $app => $deepl) {
            $this->assertSame($deepl, $metodo->invoke($provider, $app), 'Código ' . $app);
        }

        // Código desconocido: se envía en mayúsculas tal cual (deja que DeepL
        // responda; no se inventa una equivalencia).
        $this->assertSame('XX', $metodo->invoke($provider, 'xx'));
        $this->assertSame('ES', $metodo->invoke($provider, '  es  '));
    }

    public function testTranslateBatchMapsCodesPreservesEmptyAndSendsOnlyFilled(): void {
        $provider = new DeepLTranslateProviderDouble();

        $resultado = $provider->translateBatch(
            ['nombre' => 'Accion', 'descripcion' => '', 'slug' => 'accion'],
            'es',
            'en'
        );

        // Reasignación por posición y vacíos intactos.
        $this->assertSame(
            ['nombre' => 'X:Accion', 'descripcion' => '', 'slug' => 'X:accion'],
            $resultado
        );

        // Solo se envió lo que tenía contenido, y con los códigos ya mapeados.
        $this->assertSame(['Accion', 'accion'], $provider->textosEnviados);
        $this->assertSame('ES', $provider->origen);
        $this->assertSame('EN', $provider->destino);
    }

    public function testTranslateBatchReturnsInputWhenNothingToTranslate(): void {
        $provider = new DeepLTranslateProviderDouble();

        $entrada = ['a' => '', 'b' => '   '];
        $this->assertSame($entrada, $provider->translateBatch($entrada, 'es', 'en'));
        $this->assertSame([], $provider->textosEnviados);
    }

    public function testRequestThrowsWhenNotConfiguredWithoutTouchingNetwork(): void {
        $provider = new DeepLTranslateProvider('');
        $metodo = new \ReflectionMethod(DeepLTranslateProvider::class, 'request');
        $metodo->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TRANSLATE_API_KEY');
        $metodo->invoke($provider, ['Hola'], 'ES', 'EN');
    }
}
