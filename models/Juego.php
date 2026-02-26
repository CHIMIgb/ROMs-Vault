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
            $params[':consola'] = $filters['consola'];
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
            $params[':categoria'] = $filters['categoria'];
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
            $params[':region'] = $filters['region'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDownloadLink($fileId) {
        return "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t";
    }
}