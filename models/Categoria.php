<?php
require_once 'Model.php';

class Categoria extends Model {
    protected $table = 'categorias';

    public function getAllPaginated(?string $busqueda, int $offset, int $limit, ?string $activo = null): array {
        $where = [];
        if ($busqueda) $where[] = "nombre ILIKE :busqueda";
        if ($activo !== null) $where[] = "activo = :activo";

        $sql = "SELECT * FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY nombre ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        if ($busqueda) $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        if ($activo !== null) $stmt->bindValue(':activo', (bool)(int)$activo, PDO::PARAM_BOOL);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(?string $busqueda = null, ?string $activo = null): int {
        $where = [];
        if ($busqueda) $where[] = "nombre ILIKE :busqueda";
        if ($activo !== null) $where[] = "activo = :activo";

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        if ($busqueda) $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        if ($activo !== null) $stmt->bindValue(':activo', (bool)(int)$activo, PDO::PARAM_BOOL);
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    public function countActivas(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM {$this->table} WHERE activo = true")->fetchColumn();
    }
}
