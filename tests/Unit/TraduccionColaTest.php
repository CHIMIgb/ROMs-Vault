<?php
/**
 * tests/Unit/TraduccionColaTest.php
 * Pruebas unitarias de la cola de traducciones asíncronas:
 *   - Validación de entrada de encolar() (sin tocar la BD).
 *   - Cálculo del backoff exponencial (función pura).
 *   - Interruptor de modo de TraduccionService (TRANSLATE_MODE).
 *   - encolar() con campos vacíos no toca la BD.
 *
 * La mecánica real contra PostgreSQL (SKIP LOCKED, dedupe, dead-letter) se
 * cubre en tests/Integration/WorkerTraduccionesTest.php.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TraduccionColaTest extends TestCase {

    /** PDO inerte: nunca se consulta (las validaciones fallan antes). */
    private function pdoInerte(): \PDO {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite no está disponible.');
        }
        return new \PDO('sqlite::memory:');
    }

    public function testEncolarRechazaEntidadInvalida(): void {
        $cola = new \TraduccionCola($this->pdoInerte());

        $this->expectException(\InvalidArgumentException::class);
        $cola->encolar('consola', 1, ['nombre' => 'x']);
    }

    public function testEncolarRechazaIdInvalido(): void {
        $cola = new \TraduccionCola($this->pdoInerte());

        $this->expectException(\InvalidArgumentException::class);
        $cola->encolar('juego', 0, ['descripcion' => 'x']);
    }

    /**
     * Backoff exponencial 2^intentos con mínimo 1 s y tope de 300 s.
     */
    public function testBackoffExponencialConTope(): void {
        $this->assertSame(1, \TraduccionCola::backoffSegundos(0));
        $this->assertSame(2, \TraduccionCola::backoffSegundos(1));
        $this->assertSame(4, \TraduccionCola::backoffSegundos(2));
        $this->assertSame(8, \TraduccionCola::backoffSegundos(3));
        $this->assertSame(16, \TraduccionCola::backoffSegundos(4));
        $this->assertSame(300, \TraduccionCola::backoffSegundos(9));
        $this->assertSame(300, \TraduccionCola::backoffSegundos(100));
    }

    public function testBackoffConIntentosNegativosNoRompe(): void {
        $this->assertSame(1, \TraduccionCola::backoffSegundos(-5));
    }

    /**
     * TRANSLATE_MODE: el modo por defecto es 'async' (la cola); solo el valor
     * exacto 'sync' recupera el comportamiento síncrono histórico.
     */
    public function testModoPorDefectoSinVariableEsAsync(): void {
        $previo = getenv('TRANSLATE_MODE');
        putenv('TRANSLATE_MODE');
        unset($_ENV['TRANSLATE_MODE']);

        $this->assertSame('async', \TraduccionService::modo());

        // restore
        if ($previo !== false) {
            putenv('TRANSLATE_MODE=' . $previo);
        }
    }

    public function testModoSyncYValoresInvalidos(): void {
        $previo = getenv('TRANSLATE_MODE');
        $previoEnv = $_ENV['TRANSLATE_MODE'] ?? null;

        $_ENV['TRANSLATE_MODE'] = 'ASYNC';
        $this->assertSame('async', \TraduccionService::modo(), 'Debe aceptar mayúsculas.');

        $_ENV['TRANSLATE_MODE'] = 'sync';
        $this->assertSame('sync', \TraduccionService::modo());

        $_ENV['TRANSLATE_MODE'] = 'pepito';
        $this->assertSame('async', \TraduccionService::modo(), 'Valor inválido cae a async (default).');

        // restore
        if ($previoEnv === null) {
            unset($_ENV['TRANSLATE_MODE']);
        } else {
            $_ENV['TRANSLATE_MODE'] = $previoEnv;
        }
        if ($previo !== false) {
            putenv('TRANSLATE_MODE=' . $previo);
        }
    }

    /**
     * Sin texto que traducir no se encola nada (y no se toca la BD: la
     * conexión apunta a un puerto inerte en la suite Unit).
     */
    public function testEncolarSinCamposNoTocaLaBd(): void {
        $this->assertSame(0, \TraduccionService::encolar('juego', 1, ['descripcion' => '   ']));
        $this->assertSame(0, \TraduccionService::encolar('categoria', 1, []));
    }
}
