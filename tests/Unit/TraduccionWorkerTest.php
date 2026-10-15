<?php
/**
 * tests/Unit/TraduccionWorkerTest.php
 * Núcleo de drenado de la cola de traducciones (config/TraduccionWorker.php):
 *   - procesarUno() devuelve null con la cola vacía;
 *   - clasificación de motivos permanentes (dead-letter) sin tocar la red;
 *   - drenar() respeta el máximo y no hace trabajo de más;
 *   - drenarOportunista() es inerte sin proveedor configurado (fail-open).
 *
 * Usa una cola falsa (sin BD) y un motivo permanente real ('sin_texto' al pasar
 * campos vacíos), así que TraduccionService::traducirEntidad() retorna antes de
 * construir ningún proveedor: los tests son puros y no salen a la red.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\ColaFalsa;

class TraduccionWorkerTest extends TestCase {

    /** Job mínimo; campos vacíos => traducirEntidad() responde 'sin_texto'. */
    private function job(int $id, array $campos = []): array {
        return [
            'id'           => $id,
            'entidad'      => 'juego',
            'entidad_id'   => $id,
            'campos'       => $campos,
            'intentos'     => 1,
            'max_intentos' => 5,
        ];
    }

    public function testProcesarUnoConColaVaciaDevuelveNull(): void {
        $worker = new \TraduccionWorker(new ColaFalsa([]));
        $this->assertNull($worker->procesarUno());
    }

    public function testDrenarConColaVaciaNoProcesaNada(): void {
        $worker = new \TraduccionWorker(new ColaFalsa([]));
        $this->assertSame(
            ['procesados' => 0, 'ok' => 0, 'error' => 0],
            $worker->drenar(3, 5.0)
        );
    }

    /**
     * Un motivo permanente (sin_texto) va directo a dead-letter: ni se pierde
     * el trabajo ni se queman reintentos.
     */
    public function testProcesarUnoMarcaDeadLetterEnMotivoPermanente(): void {
        $cola   = new ColaFalsa([$this->job(101)]);
        $worker = new \TraduccionWorker($cola);

        $r = $worker->procesarUno();

        $this->assertNotNull($r);
        $this->assertFalse($r['ok']);
        $this->assertSame('sin_texto', $r['motivo']);
        $this->assertTrue($r['permanente']);
        $this->assertSame([['fallido', 101, 'sin_texto']], $cola->llamadas);
    }

    public function testDrenarRespetaElMaximoDeTrabajos(): void {
        $cola   = new ColaFalsa([$this->job(1), $this->job(2), $this->job(3)]);
        $worker = new \TraduccionWorker($cola);

        $resumen = $worker->drenar(2, 0.0);

        $this->assertSame(2, $resumen['procesados']);
        $this->assertCount(2, $cola->llamadas, 'No debe procesar más allá del máximo.');
    }

    public function testDrenarMaxCeroProcesaTodaLaCola(): void {
        $cola   = new ColaFalsa([$this->job(1), $this->job(2)]);
        $worker = new \TraduccionWorker($cola);

        $resumen = $worker->drenar(0, 0.0);

        $this->assertSame(2, $resumen['procesados']);
        $this->assertSame(2, $resumen['error']);
    }

    /**
     * Sin proveedor configurado el drenado oportunista es inerte: no construye
     * la cola, no reclama y no quema intentos (fail-open).
     */
    public function testDrenarOportunistaSinProveedorEsInerte(): void {
        $previo = $_ENV['TRANSLATE_PROVIDER'] ?? null;
        $_ENV['TRANSLATE_PROVIDER'] = 'none';
        putenv('TRANSLATE_PROVIDER=none');
        try {
            $this->assertSame(
                ['procesados' => 0, 'ok' => 0, 'error' => 0],
                \TraduccionWorker::drenarOportunista()
            );
        } finally {
            if ($previo === null) {
                unset($_ENV['TRANSLATE_PROVIDER']);
            } else {
                $_ENV['TRANSLATE_PROVIDER'] = $previo;
            }
            putenv('TRANSLATE_PROVIDER=' . ($previo ?? ''));
        }
    }
}
