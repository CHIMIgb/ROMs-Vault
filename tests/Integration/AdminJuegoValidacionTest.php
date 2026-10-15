<?php
/**
 * tests/Integration/AdminJuegoValidacionTest.php
 * Validación de longitudes de los campos de un juego al guardarlo desde el
 * panel admin:
 *  - un valor que desborda la columna ya no provoca HTTP 500: se repinta el
 *    formulario (200) con un aviso y sin persistir nada;
 *  - el valor enviado se conserva en el formulario de edición (no se pierde
 *    la entrada del usuario);
 *  - el límite es inclusivo (50 caracteres se aceptan).
 *
 * Cubre la regresión de producción: `formato_imagen VARCHAR(20)` con el valor
 * "Windows Binary (.exe)" (21) lanzaba
 * PDOException 22001 (value too long for type character varying(20)) => 500.
 */

namespace Tests\Integration;

class AdminJuegoValidacionTest extends IntegrationTestCase {

    private static function unique(string $base): string {
        return $base . '-' . substr((string) uniqid(), -6);
    }

    /**
     * Crea una consola y una categoría mínimas y devuelve [consolaId, catId].
     *
     * @return array{0: int, 1: int}
     */
    private function crearConsolaYCategoria(): array {
        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([self::unique('Val Consola')]);
        $consolaId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO categorias (nombre, activo) VALUES (?, TRUE)'
        )->execute([self::unique('Val Cat')]);
        $catId = (int) $this->pdo()->lastInsertId();

        return [$consolaId, $catId];
    }

    /**
     * Cuerpo base de un juego válido, sobrescribible con $extra.
     *
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function datosJuego(int $consolaId, int $catId, string $titulo, array $extra = []): array {
        return array_merge([
            'titulo'                 => $titulo,
            'descripcion'            => 'Descripcion de prueba',
            'consola_id'             => (string) $consolaId,
            'categoria_id'           => (string) $catId,
            'region'                 => 'PAL',
            'fecha_lanzamiento'      => '',
            'idiomas'                => 'Español',
            'formato_imagen'         => 'ISO',
            'game_id_code'           => 'VAL-001',
            'google_drive_file_id'   => 'drive-' . substr((string) uniqid(), -6),
            'google_drive_view_link' => 'https://drive.google.com/file/d/demo/view',
            'size_bytes'             => '1024',
        ], $extra);
    }

    private function idPorTitulo(string $titulo): int {
        $stmt = $this->pdo()->prepare('SELECT id FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        return (int) $stmt->fetch()['id'];
    }

    /** El valor exacto que rompía en producción (21 chars > VARCHAR(20)). */
    public function testFormatoImagenDeLaRegresionSeAcepta(): void {
        $this->login();
        [$consolaId, $catId] = $this->crearConsolaYCategoria();
        $titulo = self::unique('Regresion Formato');
        $this->get('/admin/add');

        $resp = $this->post('/admin/add', $this->datosJuego($consolaId, $catId, $titulo, [
            'formato_imagen' => 'Windows Binary (.exe)',
        ]));

        $this->assertSame(302, $resp['status'], 'El guardado debe completarse (redireccion).');

        $stmt = $this->pdo()->prepare('SELECT formato_imagen FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        $this->assertSame('Windows Binary (.exe)', (string) $stmt->fetch()['formato_imagen']);
    }

    /** Un valor demasiado largo no persiste y devuelve el formulario (200). */
    public function testCrearConCampoDemasiadoLargoNoPersisteYDevuelve200(): void {
        $this->login();
        [$consolaId, $catId] = $this->crearConsolaYCategoria();
        $titulo = self::unique('Formato Largo');
        $this->get('/admin/add');

        $resp = $this->post('/admin/add', $this->datosJuego($consolaId, $catId, $titulo, [
            'formato_imagen' => str_repeat('X', 51),
        ]));

        $this->assertSame(200, $resp['status'], 'No debe ser un 500: repinta el formulario.');
        $this->assertStringContainsString('no puede superar los 50 caracteres', $resp['body']);

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) AS n FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        $this->assertSame(0, (int) $stmt->fetch()['n'], 'No debe crearse el juego.');
    }

    /** El límite es inclusivo: 50 caracteres se aceptan. */
    public function testLimiteDe50SeAceptaInclusivo(): void {
        $this->login();
        [$consolaId, $catId] = $this->crearConsolaYCategoria();
        $titulo = self::unique('Limite Inclusivo');
        $this->get('/admin/add');

        $valor = str_repeat('Z', 50);
        $resp  = $this->post('/admin/add', $this->datosJuego($consolaId, $catId, $titulo, [
            'formato_imagen' => $valor,
        ]));

        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT formato_imagen FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        $this->assertSame($valor, (string) $stmt->fetch()['formato_imagen']);
    }

    /** Al editar, un valor inválido no persiste y se conserva en el formulario. */
    public function testEditarConCampoDemasiadoLargoConservaElValorEnviado(): void {
        $this->login();
        [$consolaId, $catId] = $this->crearConsolaYCategoria();
        $titulo = self::unique('Editar Formato');
        $this->get('/admin/add');
        $resp = $this->post('/admin/add', $this->datosJuego($consolaId, $catId, $titulo));
        $this->assertSame(302, $resp['status']);
        $id = $this->idPorTitulo($titulo);

        $largo = str_repeat('Y', 60);
        $this->get('/admin/edit/' . $id);
        $resp = $this->post('/admin/edit/' . $id, $this->datosJuego($consolaId, $catId, $titulo, [
            'formato_imagen' => $largo,
        ]));

        $this->assertSame(200, $resp['status'], 'No debe ser un 500: repinta el formulario.');
        // edit.php pinta desde $juego: debe reflejar lo que el usuario escribio.
        $this->assertStringContainsString($largo, $resp['body'], 'Debe conservarse el valor enviado.');

        $stmt = $this->pdo()->prepare('SELECT formato_imagen FROM juegos WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame('ISO', (string) $stmt->fetch()['formato_imagen'], 'No debe modificarse en BD.');
    }
}
