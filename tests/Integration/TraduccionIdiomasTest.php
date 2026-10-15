<?php
/**
 * tests/Integration/TraduccionIdiomasTest.php
 * Verifica que `juegos.idiomas` se traduce y se resuelve como contenido de BD
 * (i18n, bloque 2), junto a la descripción.
 *
 * Cubre:
 *   - upsertJuego guarda la columna idiomas.
 *   - resolverJuegos aplica la traducción de idiomas (y descripción).
 *   - Fallback a 'es' cuando no hay fila de traducción.
 *   - Una corrección manual no se pisa con una automática.
 */

namespace Tests\Integration;

use Traduccion;
use TraduccionService;

class TraduccionIdiomasTest extends IntegrationTestCase {

    /** Inserta un juego mínimo y devuelve su id. */
    private function crearJuego(string $idiomas): int {
        $this->pdo()->prepare(
            'INSERT INTO juegos (titulo, idiomas, google_drive_file_id, activo) VALUES (?, ?, ?, TRUE)'
        )->execute(['Juego Idiomas', $idiomas, 'fake-drive-' . uniqid()]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function testResolverJuegosAplicaIdiomasTraducidos(): void {
        $id = $this->crearJuego('Español, Inglés, Francés');
        (new Traduccion())->upsertJuego(
            $id, 'en', 'A racing game', 'manual', false, null, 'Spanish, English, French'
        );
        TraduccionService::resetCache();

        $filas = [['id' => $id, 'idiomas' => 'Español, Inglés, Francés', 'descripcion' => 'Texto']];
        TraduccionService::resolverJuegos($filas, 'en');

        $this->assertSame('Spanish, English, French', $filas[0]['idiomas']);
        $this->assertSame('A racing game', $filas[0]['descripcion']);
    }

    public function testResolverJuegosSinTraduccionMantieneEspanol(): void {
        $id = $this->crearJuego('Español, Inglés');
        TraduccionService::resetCache();

        $filas = [['id' => $id, 'idiomas' => 'Español, Inglés', 'descripcion' => 'Texto']];
        TraduccionService::resolverJuegos($filas, 'de');

        $this->assertSame('Español, Inglés', $filas[0]['idiomas']);
        $this->assertSame('Texto', $filas[0]['descripcion']);
    }

    public function testUpsertJuegoNoPisaManualConAuto(): void {
        $id = $this->crearJuego('Español');

        $model = new Traduccion();
        $model->upsertJuego($id, 'en', 'Manual', 'manual', true, null, 'Spanish');
        $model->upsertJuego($id, 'en', 'Auto', 'auto', false, 'hash-auto', 'Auto Spain');

        TraduccionService::resetCache();

        $filas = [['id' => $id, 'idiomas' => 'Español', 'descripcion' => 'Texto']];
        TraduccionService::resolverJuegos($filas, 'en');

        $this->assertSame('Manual', $filas[0]['descripcion']);
        $this->assertSame('Spanish', $filas[0]['idiomas']);
    }
}
