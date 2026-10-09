<?php
/**
 * tests/Integration/JuegoModelTest.php
 * Regresión del hallazgo de la auditoría IDOR (2.3): findByFileId() debe
 * devolver SOLO juegos activos (la publicación se controla con `activo`).
 */

namespace Tests\Integration;

require_once dirname(__DIR__, 2) . '/src/models/Juego.php';

class JuegoModelTest extends IntegrationTestCase {

    private function crearConsola(string $nombre): int {
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([$nombre]);
        return (int) $this->pdo()->lastInsertId();
    }

    private function crearJuego(string $titulo, string $fileId, bool $activo): int {
        $consolaId = $this->crearConsola('Consola ' . $titulo);
        $stmt = $this->pdo()->prepare(
            'INSERT INTO juegos (titulo, consola_id, google_drive_file_id, activo)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bindValue(1, $titulo, \PDO::PARAM_STR);
        $stmt->bindValue(2, $consolaId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $fileId, \PDO::PARAM_STR);
        $stmt->bindValue(4, $activo, \PDO::PARAM_BOOL);
        $stmt->execute();
        return (int) $this->pdo()->lastInsertId();
    }

    public function testFindByFileIdDevuelveJuegoActivo(): void {
        $id = $this->crearJuego('Juego Activo', 'file-activo-123', true);

        $juego = (new \Juego())->findByFileId('file-activo-123');

        $this->assertIsArray($juego);
        $this->assertSame($id, (int) $juego['id']);
        $this->assertSame('file-activo-123', $juego['google_drive_file_id']);
    }

    public function testFindByFileIdNoDevuelveJuegoInactivo(): void {
        $this->crearJuego('Juego Inactivo', 'file-inactivo-456', false);

        $juego = (new \Juego())->findByFileId('file-inactivo-456');

        $this->assertFalse($juego, 'Un juego desactivado no debe ser jugable ni descargable.');
    }
}