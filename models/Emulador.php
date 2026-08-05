<?php
require_once 'Model.php';

class Emulador extends Model {
    protected $table = 'emuladores';

    /**
     * Devuelve los emuladores activos de una consola (principal + alterno).
     */
    public function getByConsola(int $consolaId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE consola_id = ? AND activo = true ORDER BY es_alterno ASC"
        );
        $stmt->execute([$consolaId]);
        return $stmt->fetchAll();
    }

    /**
     * Todos los emuladores con el nombre de su consola (para el panel admin).
     */
    public function getAllWithConsolas(): array {
        $stmt = $this->pdo->query(
            "SELECT e.*, c.nombre AS consola_nombre
             FROM {$this->table} e
             INNER JOIN consolas c ON c.id = e.consola_id
             ORDER BY c.nombre ASC, e.es_alterno ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Consolas que tienen emuladores, filtradas y paginadas (1 fila por consola).
     * `activo` de la fila = 1 si al menos un emulador de la consola está activo.
     */
    public function getConsolasPaginated(?string $busqueda, int $offset, int $limit, ?string $activo = null): array {
        $where = [];
        if ($busqueda) $where[] = "(c.nombre ILIKE :busqueda OR e.nombre ILIKE :busqueda)";
        if ($activo !== null) $where[] = "e.activo = :activo";

        $sql = "SELECT c.id, c.nombre AS consola_nombre,
                       MAX(CASE WHEN e.activo THEN 1 ELSE 0 END) AS activo
                FROM consolas c
                INNER JOIN {$this->table} e ON e.consola_id = c.id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " GROUP BY c.id, c.nombre ORDER BY c.nombre ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        if ($busqueda) $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        if ($activo !== null) $stmt->bindValue(':activo', (bool)(int)$activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Total de consolas con emuladores que coinciden con los filtros.
     */
    public function countConsolas(?string $busqueda = null, ?string $activo = null): int {
        $where = [];
        if ($busqueda) $where[] = "(c.nombre ILIKE :busqueda OR e.nombre ILIKE :busqueda)";
        if ($activo !== null) $where[] = "e.activo = :activo";

        $sql = "SELECT COUNT(DISTINCT c.id) AS total
                FROM consolas c
                INNER JOIN {$this->table} e ON e.consola_id = c.id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        if ($busqueda) $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        if ($activo !== null) $stmt->bindValue(':activo', (bool)(int)$activo, PDO::PARAM_BOOL);
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Emuladores de un conjunto de consolas (para agrupar en el listado admin).
     */
    public function getByConsolaIds(array $ids): array {
        $ids = array_map('intval', $ids);
        if (!$ids) return [];

        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE consola_id IN ($in) ORDER BY consola_id ASC, es_alterno ASC"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    /**
     * Invierte el estado activo de todos los emuladores de una consola.
     * Devuelve el nuevo estado (bool) o null si la consola no tiene emuladores.
     */
    public function toggleActivoByConsola(int $consolaId): ?bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE consola_id = ?");
        $stmt->execute([$consolaId]);
        if ((int) $stmt->fetchColumn() === 0) return null;

        $stmt = $this->pdo->prepare(
            "SELECT MAX(CASE WHEN activo THEN 1 ELSE 0 END) FROM {$this->table} WHERE consola_id = ?"
        );
        $stmt->execute([$consolaId]);
        $nuevo = ((int) $stmt->fetchColumn() === 1) ? 0 : 1;

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET activo = ? WHERE consola_id = ?");
        $stmt->execute([$nuevo, $consolaId]);

        return (bool) $nuevo;
    }

    /**
     * Guarda (reemplaza) el emulador principal y el alterno de una consola
     * de forma atómica. Acepta null para no guardar ese emulador.
     */
    public function replaceForConsola(int $consolaId, ?array $principal, ?array $alterno): bool {
        $sql = "INSERT INTO {$this->table} (consola_id, nombre, plataformas, url, es_alterno)
                VALUES (:consola_id, :nombre, :plataformas, :url, :es_alterno)";

        $this->pdo->beginTransaction();
        try {
            $this->deleteByConsola($consolaId);

            $stmt = $this->pdo->prepare($sql);
            foreach ([
                [$principal, false],
                [$alterno, true],
            ] as [$emulador, $esAlterno]) {
                if ($emulador === null) continue;
                $stmt->bindValue(':consola_id', $consolaId, PDO::PARAM_INT);
                $stmt->bindValue(':nombre', $emulador['nombre'], PDO::PARAM_STR);
                $stmt->bindValue(':plataformas', implode(',', $emulador['plataformas']), PDO::PARAM_STR);
                $stmt->bindValue(':url', $emulador['url'], PDO::PARAM_STR);
                $stmt->bindValue(':es_alterno', $esAlterno, PDO::PARAM_BOOL);
                $stmt->execute();
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Elimina todos los emuladores registrados para una consola.
     */
    public function deleteByConsola(int $consolaId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE consola_id = ?");
        return $stmt->execute([$consolaId]);
    }

    /**
     * Consolas que todavía no tienen ningún emulador configurado.
     * Se usan en el formulario "Registrar Emulador" para elegir la consola.
     */
    public function getConsolasSinEmulador(): array {
        $stmt = $this->pdo->query(
            "SELECT id, nombre
             FROM consolas
             WHERE id NOT IN (SELECT DISTINCT consola_id FROM {$this->table})
             ORDER BY nombre ASC"
        );
        return $stmt->fetchAll();
    }
}
