<?php
/**
 * tests/Integration/DrainEndpointTest.php
 * Endpoint interno de drenado (endpoints/drain_traducciones.php), invocado por
 * DrenadoTrigger en una petición aparte tras guardar en el admin:
 *   - sin el secreto (o con uno incorrecto) responde 403 y no toca la cola;
 *   - con el secreto correcto responde 200 con el resumen del drenado;
 *   - con el proveedor apagado (TRANSLATE_PROVIDER=none) el drenado es inerte:
 *     el trabajo sigue pendiente y no se queman intentos;
 *   - nunca filtra código fuente.
 *
 * El secreto lo inyecta tests/Integration/Server.php (INTERNAL_DRAIN_SECRET).
 */

namespace Tests\Integration;

class DrainEndpointTest extends IntegrationTestCase {

    /** Debe coincidir con INTERNAL_DRAIN_SECRET de tests/Integration/Server.php. */
    private const SECRETO = 'secreto-de-prueba-drenado';

    private const RUTA = '/endpoints/drain_traducciones.php';

    protected function setUp(): void {
        parent::setUp();
        $this->pdo()->exec('TRUNCATE TABLE public.traduccion_cola RESTART IDENTITY');
    }

    /** Encola un job pendiente directamente en la BD de prueba. */
    private function encolarJob(int $entidadId = 900): void {
        $sql = "INSERT INTO traduccion_cola (entidad, entidad_id, campos)
                VALUES ('juego', :id, '{\"descripcion\":\"x\"}'::jsonb)";
        $this->pdo()->prepare($sql)->execute([':id' => $entidadId]);
    }

    private function filaCola(): array {
        return $this->pdo()->query('SELECT * FROM traduccion_cola ORDER BY id')->fetch();
    }

    public function testSinCabeceraDevuelve403(): void {
        $resp = $this->get(self::RUTA);

        $this->assertSame(403, $resp['status']);
    }

    public function testCabeceraIncorrectaDevuelve403(): void {
        $resp = $this->get(self::RUTA, ['X-Internal-Drain' => 'no-es-el-secreto']);

        $this->assertSame(403, $resp['status']);
    }

    public function testConSecretoDevuelve200YNoTocaLaColaSinProveedor(): void {
        $this->encolarJob();

        $resp = $this->get(self::RUTA, ['X-Internal-Drain' => self::SECRETO]);

        $this->assertSame(200, $resp['status']);
        $data = json_decode((string) $resp['body'], true);
        $this->assertSame(
            ['procesados' => 0, 'ok' => 0, 'error' => 0],
            $data,
            'Sin proveedor el drenado es inerte.'
        );

        $fila = $this->filaCola();
        $this->assertSame('pendiente', $fila['estado'], 'El job no debe reclamarse sin proveedor.');
        $this->assertSame(0, (int) $fila['intentos'], 'No se queman intentos.');
        $this->assertNull($fila['bloqueado_en']);
    }

    public function testNoFiltraCodigoFuente(): void {
        $resp = $this->get(self::RUTA, ['X-Internal-Drain' => self::SECRETO]);
        $body = (string) $resp['body'];

        $this->assertStringNotContainsString('<?php', $body);
        $this->assertStringNotContainsString('INTERNAL_DRAIN_SECRET', $body);
    }
}
