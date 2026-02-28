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
        ];
        return $map[$orden] ?? 'j.titulo ASC';
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
}
