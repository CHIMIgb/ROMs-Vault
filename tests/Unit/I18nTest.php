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
 * - diccionarios es/en/fr/pt/de/ja/it/ru/tr JSON válidos y con claves paritarias
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use I18n;
use IntlHelper;

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
        $this->assertFalse(I18n::setLang('xx'));     // idioma inexistente
        $this->assertFalse(I18n::setLang('../es'));  // intento de path traversal
        // El idioma activo no se corrompe
        $this->assertSame('es', I18n::getLang());
        $this->assertSame('Catálogo', t('nav.catalogo'));
    }

    public function testSwitchIdiomasNuevos(): void {
        $esperado = [
            'fr' => 'Catalogue',
            'pt' => 'Catálogo',
            'de' => 'Katalog',
            'ja' => 'カタログ',
            'it' => 'Catalogo',
            'ru' => 'Каталог',
            'tr' => 'Katalog',
        ];
        foreach ($esperado as $lang => $valor) {
            $this->assertTrue(I18n::setLang($lang), "Debe aceptar el idioma '$lang'.");
            $this->assertSame($lang, I18n::getLang());
            $this->assertSame($valor, t('nav.catalogo'));
        }
    }

    public function testDiccionariosJsonValidosYParitarios(): void {
        I18n::setLang('es');
        $es = I18n::dictionary('es');
        $this->assertNotEmpty($es, 'El diccionario ES no debe estar vacío.');

        $esKeys = array_keys($es);
        sort($esKeys);

        foreach (I18n::LANGUAGES as $lang) {
            $dict = I18n::dictionary($lang);
            $this->assertNotEmpty($dict, "El diccionario '$lang' no debe estar vacío.");

            $keys = array_keys($dict);
            sort($keys);
            $this->assertSame($esKeys, $keys, "es.json y $lang.json deben tener exactamente las mismas claves.");
        }
    }

    public function testTodosLosTextosSonStringsNoVacios(): void {
        foreach (I18n::LANGUAGES as $lang) {
            foreach (I18n::dictionary($lang) as $key => $value) {
                $this->assertIsString($value, "El valor de '$key' ($lang) debe ser string.");
                $this->assertNotSame('', $value, "El valor de '$key' ($lang) no debe estar vacío.");
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
        $_COOKIE['rv_lang'] = 'xx';
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

    // ── EJS_language: idioma de la UI del emulador (ítem 5.3) ───────────────
    //
    // Debe mapear cada idioma de la app a un código que exista REALMENTE en
    // cdn.emulatorjs.org/stable/data/localization/. EmulatorJS no publica
    // francés (af-FR es afrikáans), así que 'fr' cae a 'en-US'.

    public function testEmulatorLanguageMapeaCadaIdiomaAlCodigoDelCdn(): void {
        $esperado = [
            'es' => 'es-ES',
            'en' => 'en-US',
            'fr' => 'en-US', // fallback: no existe fr-FR en el CDN
            'pt' => 'pt-BR',
            'de' => 'de-GER',
            'ja' => 'ja-JA',
            'it' => 'it-IT',
            'ru' => 'ru-RU',
            'tr' => 'tr-TR',
        ];
        foreach ($esperado as $lang => $codigo) {
            $this->assertTrue(I18n::setLang($lang));
            $this->assertSame($codigo, IntlHelper::emulatorLanguage(), "Idioma '$lang' → '$codigo'.");
        }
    }

    public function testEmulatorLanguageSiempreDevuelveUnCodigoPublicado(): void {
        $publicados = ['es-ES', 'en-US', 'pt-BR', 'de-GER', 'ja-JA', 'it-IT', 'ru-RU', 'tr-TR'];
        foreach (I18n::LANGUAGES as $lang) {
            I18n::setLang($lang);
            $this->assertContains(
                IntlHelper::emulatorLanguage(),
                $publicados,
                "El código para '$lang' debe existir en el CDN de EmulatorJS."
            );
        }
    }
}