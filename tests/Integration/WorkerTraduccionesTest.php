<?php
/**
 * tests/Integration/WorkerTraduccionesTest.php
 * Cola de traducciones asíncronas (TRANSLATE_MODE=async) + worker CLI:
 *   - al guardar en el admin se encola un trabajo (no se traduce inline);
 *   - dedupe: un solo job activo por entidad (se reescribe, no se duplica);
 *   - reclamo atómico (SKIP LOCKED) y ciclo de estados;
 *   - backoff y dead-letter;
 *   - el worker CLI sin proveedor no procesa y NO pierde trabajos.
 *
 * El servidor HTTP de integración arranca con TRANSLATE_PROVIDER=none y
 * TRANSLATE_MODE=async (ver Server.php): hay encolado real en la BD de prueba
 * pero ninguna llamada de red a un proveedor.
 */

namespace Tests\Integration;

class WorkerTraduccionesTest extends IntegrationTestCase {

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::limpiarCola();
    }

    protected function setUp(): void {
        parent::setUp();
        self::limpiarCola();
    }

    private static function limpiarCola(): void {
        $GLOBALS['TEST_PDO']->exec('TRUNCATE TABLE public.traduccion_cola RESTART IDENTITY');
    }

    private static function unique(string $base): string {
        return $base . ' ' . substr((string) uniqid(), -6);
    }

    /** Devuelve todas las filas de la cola (para asserts). */
    private function filasCola(): array {
        return $this->pdo()
            ->query('SELECT * FROM traduccion_cola ORDER BY id')
            ->fetchAll();
    }

    private static function cola(): \TraduccionCola {
        return new \TraduccionCola($GLOBALS['TEST_PDO']);
    }

    // ── Guardado desde el admin: encola en vez de traducir inline ─────────

    public function testGuardarJuegoEncolaTrabajoPendiente(): void {
        $this->login();

        $this->pdo()->prepare(
            'INSERT INTO consolas (nombre, activo, emulacion_online) VALUES (?, TRUE, TRUE)'
        )->execute([self::unique('Cola Consola')]);
        $consolaId = (int) $this->pdo()->lastInsertId();

        $this->pdo()->prepare(
            'INSERT INTO categorias (nombre, activo) VALUES (?, TRUE)'
        )->execute([self::unique('Cola Cat')]);
        $catId = (int) $this->pdo()->lastInsertId();

        $titulo = self::unique('Juego Cola');
        $this->get('/admin/add');
        $resp = $this->post('/admin/add', [
            'titulo'                 => $titulo,
            'descripcion'            => 'Descripción para la cola',
            'consola_id'             => (string) $consolaId,
            'categoria_id'           => (string) $catId,
            'region'                 => 'PAL',
            'fecha_lanzamiento'      => '',
            'idiomas'                => 'Español, Inglés',
            'formato_imagen'         => 'ISO',
            'game_id_code'           => 'COLA-001',
            'google_drive_file_id'   => 'drive-' . substr((string) uniqid(), -6),
            'google_drive_view_link' => 'https://drive.google.com/file/d/xxx/view',
            'size_bytes'             => '2048',
        ]);
        $this->assertSame(302, $resp['status'], 'El guardado debe responder al instante (302).');

        $stmt = $this->pdo()->prepare('SELECT id FROM juegos WHERE titulo = ?');
        $stmt->execute([$titulo]);
        $juegoId = (int) $stmt->fetch()['id'];
        $this->assertGreaterThan(0, $juegoId);

        $filas = $this->filasCola();
        $this->assertCount(1, $filas, 'Debe encolarse exactamente un trabajo.');
        $this->assertSame('juego', $filas[0]['entidad']);
        $this->assertSame($juegoId, (int) $filas[0]['entidad_id']);
        $this->assertSame('pendiente', $filas[0]['estado']);
        $this->assertSame(0, (int) $filas[0]['intentos']);

        $campos = json_decode((string) $filas[0]['campos'], true);
        $this->assertIsArray($campos);
        $this->assertSame('Descripción para la cola', $campos['descripcion'] ?? null);
        $this->assertSame('Español, Inglés', $campos['idiomas'] ?? null);

        // El guardado asíncrono NO escribe traducciones (no hay proveedor).
        $this->assertSame(
            0,
            (int) $this->pdo()->query('SELECT COUNT(*) AS c FROM juegos_traducciones')->fetch()['c']
        );
    }

    public function testGuardarCategoriaEncolaTrabajo(): void {
        $this->login();

        $nombre = self::unique('Categoria Cola');
        $this->get('/categoria/add');
        $resp = $this->post('/categoria/add', [
            'nombre'      => $nombre,
            'descripcion' => 'Descripción de categoría',
        ]);
        $this->assertSame(302, $resp['status']);

        $stmt = $this->pdo()->prepare('SELECT id FROM categorias WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $catId = (int) $stmt->fetch()['id'];

        $filas = $this->filasCola();
        $this->assertCount(1, $filas);
        $this->assertSame('categoria', $filas[0]['entidad']);
        $this->assertSame($catId, (int) $filas[0]['entidad_id']);
        $this->assertSame('pendiente', $filas[0]['estado']);
    }

    // ── Dedupe ────────────────────────────────────────────────────────────

    public function testEncolarDosVecesLaMismaEntidadNoDuplica(): void {
        $cola = self::cola();

        $id1 = $cola->encolar('juego', 42, ['descripcion' => 'primera']);
        $id2 = $cola->encolar('juego', 42, ['descripcion' => 'segunda']);

        $this->assertSame($id1, $id2, 'El job activo se reescribe, no se duplica.');

        $filas = $this->filasCola();
        $this->assertCount(1, $filas);
        $campos = json_decode((string) $filas[0]['campos'], true);
        $this->assertSame('segunda', $campos['descripcion']);
        $this->assertSame('pendiente', $filas[0]['estado']);
    }

    public function testEncolarTrasCompletarCreaUnJobNuevo(): void {
        $cola = self::cola();

        $id1 = $cola->encolar('juego', 7, ['descripcion' => 'uno']);
        $cola->marcarOk($id1);

        $id2 = $cola->encolar('juego', 7, ['descripcion' => 'dos']);
        $this->assertNotSame($id1, $id2);
        $this->assertCount(2, $this->filasCola(), 'El índice único solo cubre jobs activos.');
    }

    // ── Reclamo y estados ─────────────────────────────────────────────────

    public function testReclamarDevuelveElJobYLoMarcaEnProceso(): void {
        $cola = self::cola();
        $cola->encolar('juego', 100, ['descripcion' => 'x']);

        $job = $cola->reclamar();
        $this->assertNotNull($job);
        $this->assertSame('juego', $job['entidad']);
        $this->assertSame(100, $job['entidad_id']);
        $this->assertSame(1, $job['intentos'], 'El reclamo incrementa intentos.');
        $this->assertSame(['descripcion' => 'x'], $job['campos']);

        $this->assertNull($cola->reclamar(), 'No hay más pendientes que reclamar.');

        $fila = $this->filasCola()[0];
        $this->assertSame('en_proceso', $fila['estado']);
        $this->assertNotNull($fila['bloqueado_en']);
    }

    public function testReclamarFiltraPorEntidad(): void {
        $cola = self::cola();
        $cola->encolar('juego', 1, ['descripcion' => 'juego']);
        $cola->encolar('categoria', 2, ['nombre' => 'cat']);

        $job = $cola->reclamar('categoria');
        $this->assertNotNull($job);
        $this->assertSame('categoria', $job['entidad']);
        $this->assertSame(2, $job['entidad_id']);

        $this->assertNull($cola->reclamar('categoria'), 'Ya no quedan categorías.');
        $this->assertNotNull($cola->reclamar('juego'), 'El juego sigue pendiente.');
    }

    public function testMarcarOkCierraElTrabajo(): void {
        $cola = self::cola();
        $id  = $cola->encolar('juego', 5, ['descripcion' => 'x']);
        $cola->reclamar();
        $cola->marcarOk($id);

        $fila = $this->filasCola()[0];
        $this->assertSame('ok', $fila['estado']);
    }

    public function testMarcarErrorConIntentosRestantesVuelveAPendienteConBackoff(): void {
        $cola = self::cola();
        $id  = $cola->encolar('juego', 6, ['descripcion' => 'x']);
        $cola->reclamar(); // intentos = 1

        $cola->marcarError($id, 1, 5, 'timeout del proveedor');

        $fila = $this->filasCola()[0];
        $this->assertSame('pendiente', $fila['estado']);
        $this->assertSame('timeout del proveedor', $fila['error']);
        $this->assertNull($fila['bloqueado_en']);

        // 2^1 = 2 s de espera: aún no disponible, así que no se puede reclamar.
        $this->assertNull($cola->reclamar(), 'El backoff debe aplazar el reintento.');

        $segundos = (int) $this->pdo()
            ->query("SELECT EXTRACT(EPOCH FROM (disponible_en - NOW()))::int AS s FROM traduccion_cola")
            ->fetch()['s'];
        $this->assertGreaterThan(0, $segundos, 'disponible_en debe quedar en el futuro.');
        $this->assertLessThanOrEqual(\TraduccionCola::BACKOFF_MAX, $segundos);
    }

    public function testMarcarErrorSinIntentosVaADeadLetter(): void {
        $cola = self::cola();
        $id  = $cola->encolar('juego', 8, ['descripcion' => 'x']);
        $cola->reclamar();

        $cola->marcarError($id, 5, 5, 'agotado');

        $fila = $this->filasCola()[0];
        $this->assertSame('fallido', $fila['estado']);
        $this->assertSame('agotado', $fila['error']);
    }

    public function testMarcarFallidoEsDefinitivo(): void {
        $cola = self::cola();
        $id  = $cola->encolar('juego', 9, ['descripcion' => 'x']);
        $cola->reclamar();
        $cola->marcarFallido($id, 'sin_texto');

        $fila = $this->filasCola()[0];
        $this->assertSame('fallido', $fila['estado']);
        $this->assertSame('sin_texto', $fila['error']);
        $this->assertNull($cola->reclamar(), 'Un job fallido no se reintenta.');
    }

    // ── Worker CLI ────────────────────────────────────────────────────────

    /**
     * Sin proveedor configurado el worker no arranca: los trabajos quedan
     * pendientes (no se pierden ni se queman intentos).
     */
    public function testWorkerCliSinProveedorNoProcesaNiPierdeTrabajos(): void {
        $cola = self::cola();
        $cola->encolar('juego', 11, ['descripcion' => 'x']);

        $script = dirname(__DIR__, 2) . '/scripts/worker-traducciones.php';
        $this->assertFileExists($script);

        $env = array_merge(getenv(), [
            'TRANSLATE_PROVIDER' => 'none',
            'TRANSLATE_API_KEY'  => '',
            'TRANSLATE_MODE'     => 'async',
        ]);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open([PHP_BINARY, $script, '--una-vez'], $descriptors, $pipes, null, $env);
        $this->assertIsResource($proc, 'No se pudo lanzar el worker.');

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proc);

        $this->assertSame(1, $codigo, "El worker debe salir con error.\nSTDOUT: {$stdout}\nSTDERR: {$stderr}");
        $this->assertStringContainsString('proveedor', strtolower($stderr), 'Debe explicar la causa.');

        $fila = $this->filasCola()[0];
        $this->assertSame('pendiente', $fila['estado'], 'El trabajo sigue pendiente.');
        $this->assertSame(0, (int) $fila['intentos'], 'No se queman intentos al no arrancar.');
    }

    /**
     * El worker nunca debe hacer trabajo si se pide por HTTP: el guard de
     * PHP_SAPI lo deja salir sin tocar la cola y sin filtrar código fuente.
     */
    public function testWorkerNoHaceNadaPorHttp(): void {
        $cola = self::cola();
        $cola->encolar('juego', 12, ['descripcion' => 'x']);

        $resp = $this->get('/scripts/worker-traducciones.php');
        $body = (string) ($resp['body'] ?? '');

        $this->assertNotSame(500, $resp['status']);
        $this->assertStringNotContainsString('<?php', $body, 'No debe filtrarse el código fuente.');
        $this->assertStringNotContainsString('PHP_SAPI', $body);

        $fila = $this->filasCola()[0];
        $this->assertSame('pendiente', $fila['estado'], 'El job no debe procesarse por HTTP.');
        $this->assertSame(0, (int) $fila['intentos']);
        $this->assertNull($fila['bloqueado_en']);
    }
}
