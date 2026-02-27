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

    // Método con paginación y búsqueda
    public function getWithRelationsPaginated($filters = [], $offset = 0, $limit = 20) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true";

        // Añadir búsqueda por título si existe
        if (!empty($filters['busqueda'])) {
            $sql .= " AND j.titulo ILIKE :busqueda"; // ILIKE para PostgreSQL (case insensitive)
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

        $sql .= " ORDER BY j.titulo ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind de parámetros de búsqueda
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        
        // Bind de parámetros de filtros
        if (!empty($filters['consola'])) {
            $stmt->bindValue(':consola', $filters['consola'], PDO::PARAM_INT);
        }
        if (!empty($filters['categoria'])) {
            $stmt->bindValue(':categoria', $filters['categoria'], PDO::PARAM_INT);
        }
        if (!empty($filters['region'])) {
            $stmt->bindValue(':region', $filters['region'], PDO::PARAM_STR);
        }
        
        // Bind de parámetros de paginación
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar juegos con filtros y búsqueda
    public function countWithFilters($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM juegos j WHERE j.activo = true";

        // Añadir búsqueda por título si existe
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
        
        // Bind de parámetros de búsqueda
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        
        // Bind de parámetros de filtros
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