<?php
/**
 * tests/Unit/I18nTest.php
 * Unit tests del cargador de diccionarios (config/i18n.php, ítem 6.1).
 *
 * Comprueba el contrato de t():
 * - siempre devuelve string (nunca lanza)
 * - idioma activo por defecto 'es' (I18N_LANG)
 * - placeholders {nombre}
 * - fallback: clave ausente → devuelve la propia clave
 * - idioma inválido → se rechaza y no corrompe el idioma activo
 * - diccionarios es/en JSON válidos y con claves paritarias
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use I18n;

class I18nTest extends TestCase {

    protected function tearDown(): void {
        // Los tests mutan el idioma activo: siempre restaurar 'es'
        I18n::setLang('es');
        parent::tearDown();
    }

    public function testTraduccionBasicaEspanol(): void {
        I18n::setLang('es');
        $this->assertSame('Catálogo', t('nav.catalogo'));
        $this->assertSame('Usuario', t('auth.usuario'));
    }

    public function testSwitchIdiomaIngles(): void {
        I18n::setLang('en');
        $this->assertSame('en', I18n::getLang());
        $this->assertSame('Catalog', t('nav.catalogo'));
        $this->assertSame('Username', t('auth.usuario'));
    }

    public function testPlaceholderSustituido(): void {
        I18n::setLang('es');
        $this->assertSame('Hola, Ana', t('common.saludo', ['nombre' => 'Ana']));
        // Con valor numérico también
        $this->assertSame('Hola, 7', t('common.saludo', ['nombre' => 7]));
    }

    public function testClaveInexistenteDevuelveLaClave(): void {
        I18n::setLang('es');
        $this->assertSame('clave.que.no.existe', t('clave.que.no.existe'));
        // Con placeholders que no existen, no debe lanzar
        $this->assertSame('a.otra.clave', t('a.otra.clave', ['x' => 'y']));
    }

    public function testIdiomaInvalidoEsRechazado(): void {
        I18n::setLang('es');
        $this->assertFalse(I18n::setLang('fr'));
        $this->assertFalse(I18n::setLang('../es'));  // intento de path traversal
        // El idioma activo no se corrompe
        $this->assertSame('es', I18n::getLang());
        $this->assertSame('Catálogo', t('nav.catalogo'));
    }

    public function testDiccionariosJsonValidosYParitarios(): void {
        I18n::setLang('es');
        $es = I18n::dictionary('es');
        $en = I18n::dictionary('en');

        $this->assertNotEmpty($es, 'El diccionario ES no debe estar vacío.');
        $this->assertNotEmpty($en, 'El diccionario EN no debe estar vacío.');

        $esKeys = array_keys($es);
        $enKeys = array_keys($en);
        sort($esKeys);
        sort($enKeys);
        $this->assertSame($esKeys, $enKeys, 'es.json y en.json deben tener exactamente las mismas claves.');
    }

    public function testTodosLosTextosSonStringsNoVacios(): void {
        $es = I18n::dictionary('es');
        $en = I18n::dictionary('en');
        foreach ([$es, $en] as $dict) {
            foreach ($dict as $key => $value) {
                $this->assertIsString($value, "El valor de '$key' debe ser string.");
                $this->assertNotSame('', $value, "El valor de '$key' no debe estar vacío.");
            }
        }
    }
}