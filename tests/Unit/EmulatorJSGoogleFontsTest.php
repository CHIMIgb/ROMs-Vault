<?php
/**
 * tests/Unit/EmulatorJSGoogleFontsTest.php
 * Hosts externos centralizados (config/EmulatorJS.php y config/GoogleFonts.php):
 *   - URLs derivadas de cada origen (CDN de EmulatorJS, Google Fonts);
 *   - sincronía del @import de public/css/modules/variables.css (el CSS no puede
 *     leer constantes PHP: el test es la garantía de que no divergen);
 *   - CSP (config/SecurityHeaders.php): directivas que necesita el emulador,
 *     host público de R2 desde el entorno y host de Drive dentro de la allowlist.
 *
 * Son tests puros: no tocan la BD ni abren sockets.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmulatorJSGoogleFontsTest extends TestCase {

    /**
     * Ejecuta $fn con las variables de entorno indicadas y las restaura después.
     * Escribe en $_ENV y putenv() porque las factories leen $_ENV primero.
     */
    private function conEnv(array $vars, callable $fn) {
        $previos = [];
        foreach ($vars as $clave => $valor) {
            $previos[$clave] = $_ENV[$clave] ?? null;
            $_ENV[$clave] = $valor;
            putenv("$clave=$valor");
        }

        try {
            return $fn();
        } finally {
            foreach ($previos as $clave => $valor) {
                if ($valor === null) {
                    unset($_ENV[$clave]);
                    putenv($clave);
                } else {
                    $_ENV[$clave] = $valor;
                    putenv("$clave=$valor");
                }
            }
        }
    }

    public function testUrlsDerivadasDelCdnDeEmulatorJS(): void {
        $this->assertSame('https://cdn.emulatorjs.org', \EmulatorJS::CDN);
        $this->assertSame('https://cdn.emulatorjs.org/stable/data/', \EmulatorJS::dataUrl());
        $this->assertSame(
            'https://cdn.emulatorjs.org/stable/data/loader.js',
            \EmulatorJS::loaderUrl()
        );
        $this->assertStringStartsWith(\EmulatorJS::CDN . '/', \EmulatorJS::dataUrl());
    }

    public function testOrigenesDeGoogleFonts(): void {
        $this->assertSame('https://fonts.googleapis.com', \GoogleFonts::CSS_ORIGIN);
        $this->assertSame('https://fonts.gstatic.com', \GoogleFonts::FILES_ORIGIN);
        $this->assertStringStartsWith(\GoogleFonts::CSS_ORIGIN . '/css2?', \GoogleFonts::CSS_URL);
        $this->assertStringContainsString('Press+Start+2P', \GoogleFonts::CSS_URL);
        $this->assertStringContainsString('Courier+Prime', \GoogleFonts::CSS_URL);
    }

    public function testElImportDelCssCoincideConLaConstante(): void {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/modules/variables.css');

        $this->assertStringContainsString(
            "@import url('" . \GoogleFonts::CSS_URL . "');",
            $css,
            'variables.css y GoogleFonts::CSS_URL deben apuntar a la misma URL'
        );
    }

    public function testLaCspPermiteElCdnYLasFuentes(): void {
        $csp = \SecurityHeaders::csp();

        $this->assertStringContainsString(
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.emulatorjs.org;",
            $csp
        );
        $this->assertStringContainsString(
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.emulatorjs.org;",
            $csp
        );
        $this->assertStringContainsString(
            "font-src 'self' https://fonts.gstatic.com data: https://cdn.emulatorjs.org;",
            $csp
        );
    }

    public function testLaCspMantieneLasExcepcionesDelEmulador(): void {
        $csp = \SecurityHeaders::csp();

        foreach ([
            "media-src 'self' blob: https://cdn.emulatorjs.org;",
            "connect-src 'self' blob: https://cdn.emulatorjs.org;",
            "worker-src 'self' blob: https://cdn.emulatorjs.org;",
            "default-src 'self';",
            "frame-ancestors 'self';",
            "base-uri 'self';",
            "form-action 'self';",
            "object-src 'none'",
        ] as $fragmento) {
            $this->assertStringContainsString($fragmento, $csp);
        }

        // Las 12 directivas de la política anterior siguen presentes.
        foreach ([
            'default-src', 'script-src', 'style-src', 'font-src', 'img-src',
            'media-src', 'connect-src', 'worker-src', 'frame-ancestors',
            'base-uri', 'form-action', 'object-src',
        ] as $directiva) {
            $this->assertStringContainsString($directiva . ' ', $csp, "Falta la directiva $directiva");
        }
    }

    public function testLaCspIncluyeElHostDeR2DesdeElEntorno(): void {
        $this->conEnv(
            ['R2_PUBLIC_HOST' => 'https://pub-ejemplo.r2.dev'],
            function () {
                $this->assertStringContainsString(
                    "img-src 'self' data: blob: https://cdn.emulatorjs.org https://pub-ejemplo.r2.dev;",
                    \SecurityHeaders::csp()
                );
            }
        );
    }

    public function testLaCspUsaR2PublicBaseComoReserva(): void {
        $this->conEnv(
            ['R2_PUBLIC_HOST' => '', 'R2_PUBLIC_BASE' => 'https://pub-otro.r2.dev'],
            function () {
                $this->assertStringContainsString(
                    'https://pub-otro.r2.dev',
                    \SecurityHeaders::csp()
                );
            }
        );
    }

    public function testLaCspSinR2NoAnadeHostAlguno(): void {
        $this->conEnv(['R2_PUBLIC_HOST' => '', 'R2_PUBLIC_BASE' => ''], function () {
            $this->assertStringContainsString(
                "img-src 'self' data: blob: https://cdn.emulatorjs.org;",
                \SecurityHeaders::csp()
            );
        });
    }

    public function testElHostPublicoDeDriveSigueEnLaAllowlist(): void {
        $host = (string) parse_url(\GDriveClient::UC_BASE, PHP_URL_HOST);

        $this->assertSame('drive.google.com', $host);
        $this->assertContains($host, \GDriveAllowlist::HOSTS_DEFAULT);
    }
}
