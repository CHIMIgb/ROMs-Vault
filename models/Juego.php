<?php
require_once 'Model.php';

class Juego extends Model {
    protected $table = 'juegos';

    public function find($id) {
        if (!$id) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result;
    }

    // Buscar juego por google_drive_file_id
    public function findByFileId($fileId) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE google_drive_file_id = ?");
        $stmt->execute([$fileId]);
        return $stmt->fetch();
    }
    
    // Obtener un juego por ID con detalles de relaciones
    public function findWithDetails($id) {
        $stmt = $this->pdo->prepare("SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.id = ? AND j.activo = true");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Incrementar contador de descargas
    public function incrementDownloads($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET downloads_count = downloads_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Incrementar contador de jugadas online
    public function incrementPlays($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET plays_count = plays_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getWithRelations($filters = []) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true";

        $params = [];

        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
            $params['consola'] = $filters['consola'];
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
            $params['categoria'] = $filters['categoria'];
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
            $params['region'] = $filters['region'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDownloadLink($fileId) {
        return "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t";
    }

    // Todos los juegos (activos e inactivos) paginados para el dashboard
    public function getAllPaginated($offset = 0, $limit = 20, $busqueda = null) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id";

        if ($busqueda) {
            $sql .= " WHERE j.titulo ILIKE :busqueda";
        }

        $sql .= " ORDER BY j.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        if ($busqueda) {
            $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar todos los juegos (activos e inactivos) para la paginación del dashboard
    public function countAll($busqueda = null) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";

        if ($busqueda) {
            $sql .= " WHERE titulo ILIKE :busqueda";
        }

        $stmt = $this->pdo->prepare($sql);

        if ($busqueda) {
            $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }

    /**
     * Whitelist de ordenamientos permitidos para el catálogo público.
     * Previene inyección SQL al construir el ORDER BY dinámicamente.
     */
    private function resolveOrder(string $orden): string {
        $map = [
            'titulo'    => 'j.titulo ASC',
            'recientes' => 'j.created_at DESC',
            'descargas' => 'j.downloads_count DESC',
            'jugados'   => 'j.plays_count DESC',
            'año_asc'   => 'j.fecha_lanzamiento ASC NULLS LAST',
            'año_desc'  => 'j.fecha_lanzamiento DESC NULLS LAST',
            'random'    => 'RANDOM()',
        ];
        return $map[$orden] ?? 'RANDOM()';
    }

    // Método con paginación, búsqueda y ordenamiento (catálogo público)
    public function getWithRelationsPaginated($filters = [], $offset = 0, $limit = 20) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true";

        if (!empty($filters['busqueda'])) {
            $sql .= " AND j.titulo ILIKE :busqueda";
        }
        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
        }

        // Ordenamiento seguro mediante whitelist
        $orden = $this->resolveOrder($filters['orden'] ?? '');
        $sql .= " ORDER BY {$orden} LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        if (!empty($filters['consola'])) {
            $stmt->bindValue(':consola', $filters['consola'], PDO::PARAM_INT);
        }
        if (!empty($filters['categoria'])) {
            $stmt->bindValue(':categoria', $filters['categoria'], PDO::PARAM_INT);
        }
        if (!empty($filters['region'])) {
            $stmt->bindValue(':region', $filters['region'], PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar juegos con filtros y búsqueda (catálogo público)
    public function countWithFilters($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM juegos j WHERE j.activo = true";

        if (!empty($filters['busqueda'])) {
            $sql .= " AND j.titulo ILIKE :busqueda";
        }
        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
        }

        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        if (!empty($filters['consola'])) {
            $stmt->bindValue(':consola', $filters['consola'], PDO::PARAM_INT);
        }
        if (!empty($filters['categoria'])) {
            $stmt->bindValue(':categoria', $filters['categoria'], PDO::PARAM_INT);
        }
        if (!empty($filters['region'])) {
            $stmt->bindValue(':region', $filters['region'], PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }

    // ── ADMIN: filtros extendidos ─────────────────────────────────────────

    /**
     * Construye la cláusula WHERE compartida para las consultas del admin.
     * Devuelve ['sql' => string, 'params' => array]
     */
    private function buildAdminWhere(array $f): array {
        $where  = [];
        $params = [];

        if (!empty($f['busqueda'])) {
            $where[]              = "j.titulo ILIKE :busqueda";
            $params['busqueda']   = '%' . $f['busqueda'] . '%';
        }
        if (!empty($f['consola'])) {
            $where[]            = "j.consola_id = :consola";
            $params['consola']  = (int)$f['consola'];
        }
        if (!empty($f['categoria'])) {
            $where[]              = "j.categoria_id = :categoria";
            $params['categoria']  = (int)$f['categoria'];
        }
        if (!empty($f['region'])) {
            $where[]           = "j.region = :region";
            $params['region']  = $f['region'];
        }
        // activo: '' = todos, '1' = activos, '0' = inactivos
        if (isset($f['activo']) && $f['activo'] !== '') {
            $where[]           = "j.activo = :activo";
            $params['activo']  = (bool)(int)$f['activo'];
        }

        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        return ['sql' => $sql, 'params' => $params];
    }

    // Todos los juegos paginados para el dashboard con filtros extendidos
    public function getAllPaginatedFiltered(array $filters, int $offset, int $limit): array {
        $w = $this->buildAdminWhere($filters);

        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre
                FROM juegos j
                LEFT JOIN consolas c   ON j.consola_id   = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id"
             . $w['sql']
             . " ORDER BY j.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($w['params'] as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
            $stmt->bindValue(":$k", $v, $type);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar con filtros extendidos (admin)
    public function countAllFiltered(array $filters): int {
        $w   = $this->buildAdminWhere($filters);
        $sql = "SELECT COUNT(*) as total FROM juegos j" . $w['sql'];
        $stmt = $this->pdo->prepare($sql);
        foreach ($w['params'] as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
            $stmt->bindValue(":$k", $v, $type);
        }
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    // ── ADMIN: estadísticas globales reales ───────────────────────────────

    public function getGlobalStats(): array {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                            AS total,
                COUNT(*) FILTER (WHERE activo)      AS activos,
                COUNT(*) FILTER (WHERE NOT activo)  AS inactivos,
                COALESCE(SUM(downloads_count), 0)   AS total_descargas,
                COALESCE(SUM(plays_count), 0)       AS total_jugadas
             FROM juegos"
        )->fetch();

        return [
            'total'            => (int)$row['total'],
            'activos'          => (int)$row['activos'],
            'inactivos'        => (int)$row['inactivos'],
            'total_descargas'  => (int)$row['total_descargas'],
            'total_jugadas'    => (int)$row['total_jugadas'],
        ];
    }

    // Top N juegos por descargas
    public function getTopByDownloads(int $n = 5): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.titulo, c.nombre as consola_nombre, j.downloads_count
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.downloads_count > 0
             ORDER BY j.downloads_count DESC
             LIMIT ?"
        );
        $stmt->execute([$n]);
        return $stmt->fetchAll();
    }

    // Top N juegos por jugadas online
    public function getTopByPlays(int $n = 5): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.titulo, c.nombre as consola_nombre, j.plays_count
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.plays_count > 0
             ORDER BY j.plays_count DESC
             LIMIT ?"
        );
        $stmt->execute([$n]);
        return $stmt->fetchAll();
    }

    /**
     * Juegos relacionados: misma consola O misma categoría, excluyendo el actual.
     * Devuelve hasta $limit resultados mezclados y ordenados por descargas.
     */
    public function getRelated(int $juegoId, int $consolaId, int $categoriaId, int $limit = 8): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre,
                    CASE
                        WHEN j.consola_id  = :consola_id   THEN 2
                        WHEN j.categoria_id = :categoria_id THEN 1
                        ELSE 0
                    END AS relevancia
             FROM juegos j
             LEFT JOIN consolas    c   ON j.consola_id   = c.id
             LEFT JOIN categorias  cat ON j.categoria_id = cat.id
             WHERE j.activo = true
               AND j.id != :juego_id
               AND (j.consola_id = :consola_id2 OR j.categoria_id = :categoria_id2)
             ORDER BY relevancia DESC, (j.downloads_count * RANDOM()) DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':juego_id',    $juegoId,    PDO::PARAM_INT);
        $stmt->bindValue(':consola_id',  $consolaId,  PDO::PARAM_INT);
        $stmt->bindValue(':categoria_id',$categoriaId,PDO::PARAM_INT);
        $stmt->bindValue(':consola_id2', $consolaId,  PDO::PARAM_INT);
        $stmt->bindValue(':categoria_id2',$categoriaId,PDO::PARAM_INT);
        $stmt->bindValue(':lim',         $limit,      PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Autocompletado: busca títulos que coincidan con el término.
     * Devuelve id, titulo, consola_nombre, imagen para sugerencias rápidas.
     */
    public function autocomplete(string $term, int $limit = 8): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.id, j.titulo, j.google_drive_file_id, j.imagen,
                    c.nombre as consola_nombre
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.titulo ILIKE :term
             ORDER BY j.downloads_count DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':term', '%' . $term . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim',  $limit,            PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

}