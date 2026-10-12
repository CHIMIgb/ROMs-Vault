<?php
require_once __DIR__ . '/Model.php';

class Consola extends Model {
    protected $table = 'consolas';

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

    public function allActivas(): array {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} WHERE activo = true ORDER BY nombre ASC");
        return $stmt->fetchAll();
    }

    /**
     * Obtener una consola por slug (URL limpia /consola/{slug}). Solo activas.
     */
    public function findBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE slug = ? AND activo = true");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * ¿Existe ya ese slug en consolas? Para la generación de slugs únicos.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->table} WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetchColumn();
    }
}
