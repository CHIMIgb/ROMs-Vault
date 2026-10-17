<?php
/**
 * tests/Unit/VistasSinScriptInlineTest.php
 *
 * Guard del Bloque 4 del plan docs/planificado/2026-10-12-plan-idiomas-db-uiux-scripts.md
 * (desglose en docs/planificado/2026-10-17-externar-scripts-vistas.md):
 *
 *   - Ninguna vista de src/views puede llevar JavaScript embebido: el JS vive
 *     en public/js/views/ y se carga con <script src="..." defer>.
 *   - Los datos que genera PHP viajan en "islas" JSON
 *     (<script type="application/json" id="rv-data-...">) que el JS lee con
 *     document.getElementById().
 *
 * Así se evita volver a meter lógica en las plantillas y que una isla se quede
 * huérfana (sin nadie que la lea) o un .js sin vista que lo cargue.
 */

use PHPUnit\Framework\TestCase;

final class VistasSinScriptInlineTest extends TestCase {
    private const VISTAS       = 'src/views';
    private const JS_VISTAS    = 'public/js/views';
    private const TIPOS_DATOS  = ['application/json', 'application/ld+json'];

    private function root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Devuelve las vistas PHP de src/views (rutas relativas con /).
     *
     * @return string[]
     */
    private function vistas(): array {
        $root  = $this->root();
        $base  = $root . '/' . self::VISTAS;
        $vistas = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $vistas[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
        sort($vistas);

        return $vistas;
    }

    /**
     * Devuelve los .js de public/js/views (rutas relativas con /).
     *
     * @return string[]
     */
    private function scriptsDeVistas(): array {
        $root  = $this->root();
        $base  = $root . '/' . self::JS_VISTAS;
        $js    = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
                continue;
            }
            $js[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
        sort($js);

        return $js;
    }

    /**
     * Todas las etiquetas <script ...> de una vista.
     *
     * @return array<int, array{linea:int, etiqueta:string, atributos:string}>
     */
    private function etiquetasScript(string $rel): array {
        $contenido = (string) file_get_contents($this->root() . '/' . $rel);
        $salida    = [];

        if (preg_match_all('/<script\b([^>]*)>/i', $contenido, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $salida;
        }
        foreach ($matches[0] as $i => $match) {
            $salida[] = [
                'linea'     => substr_count(substr($contenido, 0, $match[1]), "\n") + 1,
                'etiqueta'  => $match[0],
                'atributos' => $matches[1][$i][0],
            ];
        }

        return $salida;
    }

    public function testNoHayJavaScriptEmbebidoEnLasVistas(): void {
        $this->assertNotEmpty($this->vistas(), 'No se encontraron vistas que revisar.');

        $infractores = [];
        foreach ($this->vistas() as $rel) {
            foreach ($this->etiquetasScript($rel) as $tag) {
                $atributos = $tag['atributos'];
                if (preg_match('/\bsrc\s*=/i', $atributos) === 1) {
                    continue;
                }
                $esDatos = false;
                foreach (self::TIPOS_DATOS as $tipo) {
                    if (stripos($atributos, 'type="' . $tipo . '"') !== false) {
                        $esDatos = true;
                        break;
                    }
                }
                if (!$esDatos) {
                    $infractores[] = $rel . ':' . $tag['linea'] . ' -> ' . $tag['etiqueta'];
                }
            }
        }

        $this->assertSame(
            [],
            $infractores,
            "Hay JavaScript embebido en vistas (debe vivir en " . self::JS_VISTAS . "/*.js):\n- "
            . implode("\n- ", $infractores)
        );
    }

    public function testLosScriptsExternosDeLasVistasExisten(): void {
        $infractores = [];
        foreach ($this->vistas() as $rel) {
            foreach ($this->etiquetasScript($rel) as $tag) {
                if (preg_match('/\bsrc\s*=\s*"([^"]+)"/i', $tag['atributos'], $m) !== 1) {
                    continue;
                }
                $src = $m[1];
                // Solo rutas locales relativas: los CDN se cargan en tiempo de ejecución.
                if (preg_match('#^(https?:)?//#i', $src) === 1 || str_starts_with($src, 'data:')) {
                    continue;
                }
                if (!is_file($this->root() . '/' . ltrim($src, '/'))) {
                    $infractores[] = $rel . ':' . $tag['linea'] . ' -> ' . $src;
                }
            }
        }

        $this->assertSame(
            [],
            $infractores,
            "Vistas que cargan un <script src> inexistente:\n- " . implode("\n- ", $infractores)
        );
    }

    public function testLosScriptsDeVistasSeCarganConDefer(): void {
        $infractores = [];
        foreach ($this->vistas() as $rel) {
            foreach ($this->etiquetasScript($rel) as $tag) {
                if (preg_match('/\bsrc\s*=\s*"public\/js\/views\/[^"]+"/i', $tag['atributos']) !== 1) {
                    continue;
                }
                if (preg_match('/\bdefer\b/i', $tag['atributos']) !== 1) {
                    $infractores[] = $rel . ':' . $tag['linea'] . ' -> ' . $tag['etiqueta'];
                }
            }
        }

        $this->assertSame(
            [],
            $infractores,
            "Scripts de " . self::JS_VISTAS . " sin defer (las islas JSON se leen en DOMContentLoaded):\n- "
            . implode("\n- ", $infractores)
        );
    }

    public function testCadaIslaJsonLaLeeAlgunJs(): void {
        $js = $this->scriptsDeVistas();
        $this->assertNotEmpty($js, 'No se encontraron scripts en ' . self::JS_VISTAS . '.');

        $fuentes = [];
        foreach ($js as $rel) {
            $fuentes[$rel] = (string) file_get_contents($this->root() . '/' . $rel);
        }

        $ids = [];
        foreach ($this->vistas() as $rel) {
            foreach ($this->etiquetasScript($rel) as $tag) {
                if (preg_match('/\bid\s*=\s*"(rv-data-[^"]+)"/i', $tag['atributos'], $m) !== 1) {
                    continue;
                }
                $ids[] = ['id' => $m[1], 'origen' => $rel . ':' . $tag['linea']];
            }
        }
        $this->assertNotEmpty($ids, 'No se encontraron islas JSON en las vistas.');

        $huerfanas = [];
        foreach ($ids as $isla) {
            $leida = false;
            foreach ($fuentes as $fuente) {
                if (str_contains($fuente, "getElementById('" . $isla['id'] . "')")) {
                    $leida = true;
                    break;
                }
            }
            if (!$leida) {
                $huerfanas[] = $isla['origen'] . ' -> ' . $isla['id'] . ' (nadie la lee)';
            }
        }

        $this->assertSame(
            [],
            $huerfanas,
            "Islas JSON sin lector en " . self::JS_VISTAS . ":\n- " . implode("\n- ", $huerfanas)
        );
    }

    public function testCadaJsDeVistasLoUsaAlgunaVista(): void {
        $fuentes = [];
        foreach ($this->vistas() as $rel) {
            $fuentes[$rel] = (string) file_get_contents($this->root() . '/' . $rel);
        }

        $huerfanos = [];
        foreach ($this->scriptsDeVistas() as $rel) {
            $usado = false;
            foreach ($fuentes as $fuente) {
                if (str_contains($fuente, 'src="' . $rel . '"')) {
                    $usado = true;
                    break;
                }
            }
            if (!$usado) {
                $huerfanos[] = $rel . ' (ninguna vista lo carga)';
            }
        }

        $this->assertSame(
            [],
            $huerfanos,
            "Scripts huérfanos en " . self::JS_VISTAS . ":\n- " . implode("\n- ", $huerfanos)
        );
    }
}
