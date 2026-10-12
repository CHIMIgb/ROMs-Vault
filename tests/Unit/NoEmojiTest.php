<?php
/**
 * tests/Unit/NoEmojiTest.php
 *
 * Guard de la regla "No emojis" de AGENTS.md: ningún archivo versionado del
 * proyecto debe contener emojis.
 *
 * Se PROHÍBEN los emojis pictográficos (rango U+1F000–U+1FAFF), las banderas
 * (U+1F1E6–U+1F1FF), el rango U+2B00–U+2BFF, y las dingbats con presentación de
 * emoji (U+2705, U+274C, U+2757, U+2764, o cualquier U+2600–U+27BF seguido de
 * U+FE0F).
 *
 * Se PERMITEN las flechas tipográficas (flecha derecha/izquierda/arriba/abajo),
 * los símbolos de UI/matemáticos usados como iconos y los glifos del design
 * retro (triángulos, círculos, aspa/check). El test solo mira texto, nunca
 * binarios (uploads, BIOS, vendor, etc.).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class NoEmojiTest extends TestCase {

    /**
     * Rutas (relativas a la raíz del repo) que se revisan.
     * Se excluyen a propósito `vendor/`, `.git/`, `.agents/`, `.opencode/`,
     * `scratch/` y los binarios de `public/uploads` y `public/bios`.
     */
    private const PATHS = [
        'src',
        'endpoints',
        'public/css',
        'public/js',
        'tests',
        'docs',
        'data',
        'README.md',
        'AGENTS.md',
        '.env.example',
        'index.php',
        'router.php',
        'composer.json',
    ];

    /** Extensiones de texto que se revisan. */
    private const EXT = [
        'php', 'json', 'js', 'css', 'md', 'sql', 'html', 'txt',
        'yml', 'yaml', 'xml', 'example',
    ];

    /**
     * Regex de emoji prohibido (con modo Unicode).
     * - pictográficos U+1F000–U+1FAFF
     * - banderas U+1F1E6–U+1F1FF
     * - U+2B00–U+2BFF
     * - dingbats emoji: U+2705/U+274C/U+2757/U+2764, o U+2600–U+27BF + U+FE0F
     */
    private const EMOJI_PATTERN =
        '/[\x{1F000}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\x{2B00}-\x{2BFF}\x{2705}\x{274C}\x{2757}\x{2764}]'
        . '|[\x{2600}-\x{27BF}]\x{FE0F}/u';

    private function root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Recolecta los archivos de texto a revisar.
     *
     * @return string[] Rutas absolutas.
     */
    private function collectFiles(): array {
        $root  = $this->root();
        $files = [];

        foreach (self::PATHS as $rel) {
            $full = $root . '/' . $rel;

            if (is_file($full)) {
                $files[] = $full;
                continue;
            }
            if (!is_dir($full)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if ($ext === '' && str_starts_with($file->getFilename(), '.env')) {
                    $ext = 'example';
                }
                if (in_array($ext, self::EXT, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function testNoHayEmojisEnElProyecto(): void {
        $root  = $this->root();
        $files = $this->collectFiles();
        $this->assertNotEmpty($files, 'El escaneo no encontró archivos que revisar.');

        $offenders = [];
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            if (preg_match_all(self::EMOJI_PATTERN, $content, $matches) > 0) {
                $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
                foreach (array_unique($matches[0]) as $emoji) {
                    $code = strtoupper(dechex(mb_ord($emoji, 'UTF-8')));
                    $offenders[] = $rel . ': ' . $emoji . ' (U+' . $code . ')';
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Se encontraron emojis prohibidos (ver AGENTS.md, regla \"No emojis\"):\n- "
            . implode("\n- ", $offenders)
        );
    }
}
