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

    // ── Detección de idioma (ítem 6.2) ───────────────────────────────────────

    public function testDetectLangPorCookie(): void {
        $_COOKIE['rv_lang'] = 'en';
        $this->assertSame('en', I18n::detectLangFromRequest());
        unset($_COOKIE['rv_lang']);
    }

    public function testDetectLangPorAcceptLanguage(): void {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9,es;q=0.8';
        $this->assertSame('en', I18n::detectLangFromRequest());
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testDetectLangPrefiereCookieSobreAcceptLanguage(): void {
        $_COOKIE['rv_lang'] = 'es';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $this->assertSame('es', I18n::detectLangFromRequest());
        unset($_COOKIE['rv_lang'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testDetectLangIgnoraCookieInvalida(): void {
        $_COOKIE['rv_lang'] = 'fr';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $this->assertSame('en', I18n::detectLangFromRequest());
        unset($_COOKIE['rv_lang'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testDetectLangSinIndiciosUsaI18nLangComoBase(): void {
        $had = array_key_exists('I18N_LANG', $_ENV);
        $prev = $_ENV['I18N_LANG'] ?? null;
        $_ENV['I18N_LANG'] = 'en';
        try {
            $this->assertSame('en', I18n::detectLangFromRequest());
        } finally {
            if ($had) {
                $_ENV['I18N_LANG'] = $prev;
            } else {
                unset($_ENV['I18N_LANG']);
            }
        }
    }

    public function testDetectLangDefaultEspanol(): void {
        $had = array_key_exists('I18N_LANG', $_ENV);
        $prev = $_ENV['I18N_LANG'] ?? null;
        unset($_ENV['I18N_LANG'], $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_COOKIE['rv_lang']);
        try {
            $this->assertSame('es', I18n::detectLangFromRequest());
        } finally {
            if ($had) {
                $_ENV['I18N_LANG'] = $prev;
            }
        }
    }

    public function testResetLimpiaEstadoSinDejarContaminacion(): void {
        I18n::setLang('en');
        $this->assertSame('en', I18n::getLang());

        I18n::reset();
        // getLang() es lazy: tras reset() vuelve a DETECTAR desde superglobales,
        // no debe quedarse con el idioma previo ('en').
        unset($_COOKIE['rv_lang'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $had = array_key_exists('I18N_LANG', $_ENV);
        $prev = $_ENV['I18N_LANG'] ?? null;
        unset($_ENV['I18N_LANG']);
        try {
            $this->assertSame('es', I18n::getLang());
        } finally {
            if ($had) {
                $_ENV['I18N_LANG'] = $prev;
            }
        }
    }
}