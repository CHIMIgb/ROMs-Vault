<?php
/**
 * tests/Integration/CrudTest.php
 * CRUD de consolas a través del panel admin autenticado (HTTP real):
 * crear, editar y eliminar, verificando la persistencia en la BD de prueba.
 * También comprueba la protección de borrado cuando hay juegos asociados.
 */

namespace Tests\Integration;

class CrudTest extends IntegrationTestCase {

    private static function uniqueNombre(string $base): string {
        return $base . '-' . substr((string) uniqid(), -6);
    }

    public function testCrearConsolaPersisteEnBD(): void {
        $this->login();
        $nombre = self::uniqueNombre('Consola Test');

        // GET del formulario para capturar/refrescar la cookie CSRF
        $this->get('/consola/add');

        $resp = $this->post('/consola/add', [
            'nombre'           => $nombre,
            'descripcion'      => 'Descripción de prueba',
            'fabricante'       => 'Fabricante Test',
            'emulacion_online' => '1',
        ]);

        $this->assertSame(302, $resp['status']);
        $this->assertStringContainsString('/consola/index', $resp['headers']['location'] ?? '');

        // Verificar en BD
        $stmt = $this->pdo()->prepare('SELECT * FROM consolas WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $fila = $stmt->fetch();
        $this->assertNotFalse($fila, 'La consola debe existir en la BD');
        $this->assertSame('1', (string) $fila['emulacion_online']);
    }

    public function testCrearConsolaSinNombreMuestraError(): void {
        $this->login();
        $this->get('/consola/add');

        $resp = $this->post('/consola/add', [
            'nombre'           => '',
            'descripcion'      => '',
            'fabricante'       => '',
            'emulacion_online' => '1',
        ]);

        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('El nombre de la consola es obligatorio', $resp['body']);
    }

    public function testEditarConsolaActualizaBD(): void {
        // Crear una consola directamente en la BD para tener un id real
        $nombre = self::uniqueNombre('Consola Edit');
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, descripcion, fabricante, activo, emulacion_online) '
            . 'VALUES (?, ?, ?, TRUE, TRUE)'
        )->execute([$nombre, 'Original', 'Test']);
        $id = (int) $this->pdo()->lastInsertId();

        $this->login();
        $this->get('/consola/edit/' . $id); // captura CSRF

        $nuevoNombre = self::uniqueNombre('Consola Editada');
        // emulacion_online es un checkbox: para "desmarcarlo" el campo NO debe
        // enviarse en el POST (el controlador usa isset() ? 1 : 0).
        $resp = $this->post('/consola/edit/' . $id, [
            'nombre'           => $nuevoNombre,
            'descripcion'      => 'Modificada',
            'fabricante'       => 'Otro Fab',
        ]);

        $this->assertSame(302, $resp['status']);
        $this->assertStringContainsString('/consola/index', $resp['headers']['location'] ?? '');

        $stmt = $this->pdo()->prepare('SELECT * FROM consolas WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        $this->assertSame($nuevoNombre, $fila['nombre']);
        // pgsql devuelve boolean nativo → (int) false = 0
        $this->assertSame(0, (int) $fila['emulacion_online']);
    }

    public function testEliminarConsolaSinJuegosBorra(): void {
        $nombre = self::uniqueNombre('Consola Del');
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([$nombre]);
        $id = (int) $this->pdo()->lastInsertId();

        $this->login();
        // delete exige CSRF (acepta query string o header X-CSRF-Token)
        $resp = $this->get('/consola/delete/' . $id . '?csrf_token=' . Server::csrfToken());

        $this->assertSame(302, $resp['status']);
        $this->assertStringContainsString('/consola/index', $resp['headers']['location'] ?? '');

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) AS total FROM consolas WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame(0, (int) $stmt->fetch()['total']);
    }

    public function testEliminarConsolaConJuegosAsociadosBloqueado(): void {
        // Consola con un juego asociado → no se puede borrar
        $nombre = self::uniqueNombre('Consola Con Juegos');
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([$nombre]);
        $consolaId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO juegos (titulo, consola_id, google_drive_file_id, activo) '
            . 'VALUES (?, ?, ?, TRUE)'
        )->execute(['Juego Bloqueador', $consolaId, 'fake-drive-id-' . $consolaId]);

        $this->login();
        $resp = $this->get('/consola/delete/' . $consolaId . '?csrf_token=' . Server::csrfToken());

        $this->assertSame(302, $resp['status']);
        $this->assertStringContainsString('error=has_games', $resp['headers']['location'] ?? '');

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) AS total FROM consolas WHERE id = ?');
        $stmt->execute([$consolaId]);
        $this->assertSame(1, (int) $stmt->fetch()['total'], 'La consola no debe borrarse');
    }

    public function testToggleEmulacionAjaxRequiereAdmin(): void {
        $this->login();
        $nombre = self::uniqueNombre('Consola Toggle');
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([$nombre]);
        $id = (int) $this->pdo()->lastInsertId();

        $resp = $this->get('/consola/toggleEmulacionAjax/' . $id, [
            'X-CSRF-Token'     => Server::csrfToken(),
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertSame(200, $resp['status']);
        $data = json_decode($resp['body'], true);
        $this->assertSame(true, $data['ok'] ?? null);
        $this->assertSame(0, $data['emulacion_online'] ?? 1);
    }
}
