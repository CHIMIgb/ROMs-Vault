<?php
/**
 * tests/Unit/Support/ColaFalsa.php
 * Cola de traducciones en memoria para los tests unitarios de TraduccionWorker.
 *
 * No llama al constructor de la base (no hay conexión a BD) y registra las
 * transiciones de estado en `$llamadas` para poder afirmarlas.
 */

namespace Tests\Unit\Support;

/**
 * Implementación de prueba de TraduccionCola, sin PostgreSQL.
 */
class ColaFalsa extends \TraduccionCola {

    /** @var array<int,array> */
    private array $pendientes;

    /** @var array<int,array> */
    public array $llamadas = [];

    /** @param array<int,array> $jobs */
    public function __construct(array $jobs = []) {
        // No se llama a parent::__construct(): no hay conexión a BD.
        $this->pendientes = array_values($jobs);
    }

    public function reclamar(?string $entidad = null): ?array {
        return array_shift($this->pendientes);
    }

    public function marcarOk(int $id): void {
        $this->llamadas[] = ['ok', $id];
    }

    public function marcarFallido(int $id, string $error): void {
        $this->llamadas[] = ['fallido', $id, $error];
    }

    public function marcarError(int $id, int $intentos, int $maxIntentos, string $error): void {
        $this->llamadas[] = ['error', $id, $error];
    }
}
