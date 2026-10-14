<?php
/**
 * tests/Integration/TraducirIdiomasJuegoTest.php
 * Backfill de `juegos_traducciones.idiomas` sin tocar la descripción (i18n).
 *
 * Cubre:
 *   - upsertJuegoIdiomas actualiza idiomas preservando la descripción.
 *   - upsertJuegoIdiomas no pisa una corrección manual.
 *   - traducirIdiomasJuego (dry-run) lista todos los pendientes sin proveedor.
 *   - dry-run salta lo ya sincronizado (mismo origen_hash) y lo manual.
 */

namespace Tests\Integration;

use Traduccion;
use TraduccionService;

class TraducirIdiomasJuegoTest extends IntegrationTestCase {

    /** Inserta un juego mínimo y devuelve su id. */
    private function crearJuego(string $idiomas, string $descripcion = 'Texto'): int {
        $this->pdo()->prepare(
            'INSERT INTO juegos (titulo, descripcion, idiomas, google_drive_file_id, activo) '
            . 'VALUES (?, ?, ?, ?, TRUE)'
        )->execute(['Juego Idiomas', $descripcion, $idiomas, 'fake-drive-' . uniqid()]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function testUpsertJuegoIdiomasPreservaDescripcion(): void {
        $id = $this->crearJuego('Español, Inglés');
        $model = new Traduccion();

        $model->upsertJuego($id, 'en', 'English synopsis', 'auto', false, 'h1', 'Spanish, English');
        $model->upsertJuegoIdiomas($id, 'en', 'Spanish, English, German', 'auto', false, 'h2');

        $fila = $model->getJuegos([$id], 'en')[$id];
        $this->assertSame('Spanish, English, German', $fila['idiomas']);
        $this->assertSame('English synopsis', $fila['descripcion'], 'La descripción no debe cambiar.');
    }

    public function testUpsertJuegoIdiomasNoPisaManual(): void {
        $id = $this->crearJuego('Español');
        $model = new Traduccion();

        $model->upsertJuego($id, 'en', 'Manual desc', 'manual', true, 'h1', 'Manual idiomas');
        $model->upsertJuegoIdiomas($id, 'en', 'Auto idiomas', 'auto', false, 'h2');

        $fila = $model->getJuegos([$id], 'en')[$id];
        $this->assertSame('Manual idiomas', $fila['idiomas'], 'No debe pisar una corrección manual.');
        $this->assertSame('Manual desc', $fila['descripcion']);
    }

    public function testDryRunListaTodosLosPendientes(): void {
        $id = $this->crearJuego('Español, Inglés');
        TraduccionService::resetCache();

        $res = TraduccionService::traducirIdiomasJuego(
            $id,
            ['descripcion' => 'Texto', 'idiomas' => 'Español, Inglés'],
            ['en', 'de'],
            true
        );

        $this->assertTrue($res['ok']);
        $this->assertSame(['en', 'de'], $res['pendientes']);
    }

    public function testDryRunSaltaLoYaSincronizadoPorHash(): void {
        $id     = $this->crearJuego('Español, Inglés');
        $campos = ['descripcion' => 'Texto', 'idiomas' => 'Español, Inglés'];
        $hash   = TraduccionService::hash($campos);

        (new Traduccion())->upsertJuego($id, 'en', 'syn', 'auto', false, $hash, 'Spanish, English');
        TraduccionService::resetCache();

        $res = TraduccionService::traducirIdiomasJuego($id, $campos, ['en', 'de'], true);

        $this->assertSame(['de'], $res['pendientes'], 'El idioma ya sincronizado (mismo hash) se salta.');
    }

    public function testDryRunSaltaManual(): void {
        $id = $this->crearJuego('Español');
        (new Traduccion())->upsertJuego($id, 'en', 'Manual', 'manual', true, null, 'Manual');
        TraduccionService::resetCache();

        $res = TraduccionService::traducirIdiomasJuego(
            $id,
            ['descripcion' => 'Texto', 'idiomas' => 'Español'],
            ['en', 'de'],
            true
        );

        $this->assertSame(['de'], $res['pendientes']);
    }

    public function testSinIdiomasNoEsTraducible(): void {
        $id = $this->crearJuego('', 'Solo descripcion');
        TraduccionService::resetCache();

        $res = TraduccionService::traducirIdiomasJuego(
            $id,
            ['descripcion' => 'Solo descripcion'],
            ['en'],
            true
        );

        $this->assertFalse($res['ok']);
        $this->assertSame('sin_texto', $res['motivo']);
    }

    public function testRealSinProveedorEscribeIdiomasConLaTabla(): void {
        $id = $this->crearJuego('Inglés');
        TraduccionService::resetCache();

        $res = TraduccionService::traducirIdiomasJuego(
            $id,
            ['descripcion' => 'Texto', 'idiomas' => 'Inglés'],
            ['fr', 'tr', 'ja'],
            false
        );

        $this->assertTrue($res['ok']);
        $this->assertSame(['fr', 'tr', 'ja'], $res['idiomas']);

        $model = new Traduccion();
        $this->assertSame('Anglais', $model->getJuegos([$id], 'fr')[$id]['idiomas']);
        $this->assertSame('İngilizce', $model->getJuegos([$id], 'tr')[$id]['idiomas']);
        $this->assertSame('英語', $model->getJuegos([$id], 'ja')[$id]['idiomas']);
    }

    public function testForzarRegeneraAunqueElHashCoincida(): void {
        $id     = $this->crearJuego('Inglés');
        $campos = ['descripcion' => 'Texto', 'idiomas' => 'Inglés'];
        $hash   = TraduccionService::hash($campos);

        // Fila ya sincronizada pero con un valor antiguo/incorrecto.
        (new Traduccion())->upsertJuegoIdiomas($id, 'fr', 'ValorAntiguo', 'auto', false, $hash);
        TraduccionService::resetCache();

        // Sin --forzar: se salta por hash.
        $res = TraduccionService::traducirIdiomasJuego($id, $campos, ['fr'], false, false);
        $this->assertSame([], $res['idiomas']);
        $this->assertSame('ValorAntiguo', (new Traduccion())->getJuegos([$id], 'fr')[$id]['idiomas']);

        // Con --forzar: reescribe con la tabla.
        $res2 = TraduccionService::traducirIdiomasJuego($id, $campos, ['fr'], false, true);
        $this->assertSame(['fr'], $res2['idiomas']);
        $this->assertSame('Anglais', (new Traduccion())->getJuegos([$id], 'fr')[$id]['idiomas']);
    }
}
