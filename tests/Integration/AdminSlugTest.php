<?php
/**
 * tests/Integration/AdminSlugTest.php
 * Generación de slugs desde el panel admin (ítem 7.2):
 *  - al crear una consola/juego, el slug se deriva del nombre/título
 *    (el formulario ya no incluye el campo slug; uno enviado se ignora);
 *  - al editar, se conserva el slug actual (estable, decisión D2) y un slug
 *    enviado por POST se ignora.
 */

namespace Tests\Integration;

class AdminSlugTest extends IntegrationTestCase {

    private static function unique(string $base): string {
        return $base . '-' . substr((string) uniqid(), -6);
    }

    public function testCrearConsolaGeneraSlugDesdeNombre(): void {
        $this->login();
        $nombre = self::unique('Slug Prueba Español');
        $this->get('/consola/add');

        $resp = $this->post('/consola/add', [
            'nombre'           => $nombre,
            'descripcion'      => '',
            'fabricante'       => '',
            'emulacion_online' => '1',
        ]);
        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT slug FROM consolas WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $slug = (string) $stmt->fetch()['slug'];
        // sin acentos, en minúsculas
        $this->assertStringStartsWith('slug-prueba-espanol', $slug);
    }

    public function testCrearConsolaIgnoraSlugEnviado(): void {
        $this->login();
        $nombre = self::unique('Manual Slug');
        $this->get('/consola/add');

        // Aunque se envíe un slug por POST, al CREAR se ignora y se genera del nombre.
        $resp = $this->post('/consola/add', [
            'nombre'           => $nombre,
            'slug'             => 'mi-slug-manual',
            'descripcion'      => '',
            'fabricante'       => '',
            'emulacion_online' => '1',
        ]);
        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT slug FROM consolas WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $slug = (string) $stmt->fetch()['slug'];
        $this->assertStringStartsWith('manual-slug', $slug);
        $this->assertNotSame('mi-slug-manual', $slug);
    }

    public function testEditarConsolaConservaSlugActual(): void {
        $this->login();
        $nombre = self::unique('Editar Slug');

        // Crear la consola (el slug se autogenera)
        $this->get('/consola/add');
        $this->post('/consola/add', [
            'nombre'           => $nombre,
            'descripcion'      => '',
            'fabricante'       => '',
            'emulacion_online' => '1',
        ]);
        $stmt = $this->pdo()->prepare('SELECT id, slug FROM consolas WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $row          = $stmt->fetch();
        $id           = (int) $row['id'];
        $slugOriginal = (string) $row['slug'];

        // Al editar se ignora cualquier slug enviado y se conserva el actual
        // (estable: editar NO cambia la URL, decisión D2 del ítem 7.2).
        $this->get('/consola/edit/' . $id);
        $resp = $this->post('/consola/edit/' . $id, [
            'nombre'           => $nombre,
            'slug'             => self::unique('slug-a-mano'),
            'descripcion'      => '',
            'fabricante'       => '',
            'emulacion_online' => '1',
        ]);
        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT slug FROM consolas WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame($slugOriginal, $stmt->fetch()['slug']);
    }

    public function testCrearJuegoGeneraSlugDesdeTitulo(): void {
        $this->login();

        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([self::unique('Slug Consola')]);
        $consolaId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO categorias (nombre, activo) VALUES (?, TRUE)'
        )->execute([self::unique('Slug Cat')]);
        $catId = (int) $this->pdo()->lastInsertId();

        $titulo = self::unique('Juego Slug Español');
        $this->get('/admin/add');

        $resp = $this->post('/admin/add', [
            'titulo'                 => $titulo,
            'descripcion'            => 'Descripción',
            'consola_id'             => (string) $consolaId,
            'categoria_id'           => (string) $catId,
            'region'                 => 'PAL',
            'fecha_lanzamiento'      => '',
            'idiomas'                => 'Español',
            'formato_imagen'         => 'ISO',
            'game_id_code'           => 'TEST-001',
            'google_drive_file_id'   => 'drive-' . substr((string) uniqid(), -6),
            'google_drive_view_link' => 'https://drive.google.com/file/d/xxx/view',
            'size_bytes'             => '1024',
        ]);

        // add() redirige al dashboard al crear
        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT slug FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        $slug = (string) $stmt->fetch()['slug'];
        $this->assertStringStartsWith('juego-slug-espanol', $slug);
    }
}
