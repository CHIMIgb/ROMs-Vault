<?php
/**
 * tests/Integration/EmuladorModelTest.php
 * Ejercita los métodos de EmuladorModel contra la BD PostgreSQL real de
 * prueba (no SQLite): consultas con ILIKE, joins y transacciones.
 */

namespace Tests\Integration;

require_once dirname(__DIR__, 2) . '/models/Emulador.php';

class EmuladorModelTest extends IntegrationTestCase {

    private function crearConsola(string $nombre, bool $activo = true): int {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, ?, TRUE)'
        );
        $stmt->bindValue(1, $nombre, \PDO::PARAM_STR);
        $stmt->bindValue(2, $activo, \PDO::PARAM_BOOL);
        $stmt->execute();
        return (int) $this->pdo()->lastInsertId();
    }

    private function crearEmulador(int $consolaId, string $nombre, bool $esAlterno = false, bool $activo = true): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO emuladores (consola_id, nombre, plataformas, url, es_alterno, activo) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bindValue(1, $consolaId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $nombre, \PDO::PARAM_STR);
        $stmt->bindValue(3, 'PC', \PDO::PARAM_STR);
        $stmt->bindValue(4, 'https://ejemplo.com/' . strtolower($nombre), \PDO::PARAM_STR);
        $stmt->bindValue(5, $esAlterno, \PDO::PARAM_BOOL);
        $stmt->bindValue(6, $activo, \PDO::PARAM_BOOL);
        $stmt->execute();
    }

    public function testGetConsolasSinEmulador(): void {
        $conEmulador    = $this->crearConsola('Consola Con Emulador');
        $sinEmulador    = $this->crearConsola('Consola Sin Emulador');
        $otraSinEmulador = $this->crearConsola('Otra Consola Vacía');

        $this->crearEmulador($conEmulador, 'RetroArch');

        $model = new \Emulador();
        $result = $model->getConsolasSinEmulador();

        $ids = array_map('intval', array_column($result, 'id'));
        $this->assertContains($sinEmulador, $ids);
        $this->assertContains($otraSinEmulador, $ids);
        $this->assertNotContains($conEmulador, $ids);
    }

    public function testReplaceForConsolaGuardaPrincipalYAlterno(): void {
        $consolaId = $this->crearConsola('Consola Replace');

        $model = new \Emulador();
        $ok = $model->replaceForConsola($consolaId, [
            'nombre'      => 'Principal',
            'plataformas' => ['PC', 'Linux'],
            'url'         => 'https://principal.example',
        ], [
            'nombre'      => 'Alterno',
            'plataformas' => ['Windows'],
            'url'         => 'https://alterno.example',
        ]);

        $this->assertTrue($ok);
        $registros = $model->getByConsola($consolaId);
        $this->assertCount(2, $registros);

        $principal = array_values(array_filter($registros, fn ($r) => !$r['es_alterno']));
        $alterno   = array_values(array_filter($registros, fn ($r) =>  $r['es_alterno']));
        $this->assertCount(1, $principal);
        $this->assertCount(1, $alterno);
        $this->assertSame('PC,Linux', $principal[0]['plataformas']);
        $this->assertSame('Alterno', $alterno[0]['nombre']);
    }

    public function testReplaceForConsolaSoloPrincipalEliminaAlterno(): void {
        $consolaId = $this->crearConsola('Consola Replace Uno');

        $model = new \Emulador();
        $model->replaceForConsola($consolaId, [
            'nombre'      => 'Principal',
            'plataformas' => ['PC'],
            'url'         => 'https://p.example',
        ], [
            'nombre'      => 'Alterno',
            'plataformas' => ['PC'],
            'url'         => 'https://a.example',
        ]);

        // Segundo replace con alterno = null → solo principal
        $ok = $model->replaceForConsola($consolaId, [
            'nombre'      => 'Principal Nuevo',
            'plataformas' => ['PC'],
            'url'         => 'https://p2.example',
        ], null);

        $this->assertTrue($ok);
        $registros = $model->getByConsola($consolaId);
        $this->assertCount(1, $registros);
        $this->assertFalse((bool) $registros[0]['es_alterno']);
        $this->assertSame('Principal Nuevo', $registros[0]['nombre']);
    }

    public function testToggleActivoByConsolaInvierteEstado(): void {
        $consolaId = $this->crearConsola('Consola Toggle');
        $this->crearEmulador($consolaId, 'Emu A');
        $this->crearEmulador($consolaId, 'Emu B', true);

        $model = new \Emulador();
        $nuevoEstado = $model->toggleActivoByConsola($consolaId);

        // Todos estaban activos (1) → nuevo estado = 0
        $this->assertFalse($nuevoEstado);

        $registros = $model->getByConsola($consolaId);
        foreach ($registros as $r) {
            $this->assertFalse((bool) $r['activo']);
        }

        // Segundo toggle → vuelven a activos
        $nuevoEstado2 = $model->toggleActivoByConsola($consolaId);
        $this->assertTrue($nuevoEstado2);
    }

    public function testToggleActivoByConsolaSinEmuladoresDevuelveNull(): void {
        $consolaId = $this->crearConsola('Consola Sin Emus');

        $model = new \Emulador();
        $this->assertNull($model->toggleActivoByConsola($consolaId));
    }

    public function testGetByConsolaOrdenaPrincipalAntesQueAlterno(): void {
        $consolaId = $this->crearConsola('Consola Orden');
        $this->crearEmulador($consolaId, 'Alterno Emu', true);
        $this->crearEmulador($consolaId, 'Principal Emu', false);

        $model = new \Emulador();
        $registros = $model->getByConsola($consolaId);

        $this->assertCount(2, $registros);
        $this->assertSame('Principal Emu', $registros[0]['nombre']);
        $this->assertSame('Alterno Emu', $registros[1]['nombre']);
    }
}
