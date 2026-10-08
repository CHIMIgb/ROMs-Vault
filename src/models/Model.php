<?php
require_once __DIR__ . '/../config/database.php';

abstract class Model {
    protected $pdo;
    protected $table;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data) {
        // Whitelist de nombres de columna (defensa en profundidad contra SQLi)
        $data = $this->sanitizeColumns($data);
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function update($id, $data) {
        // Whitelist de nombres de columna (defensa en profundidad contra SQLi)
        $data = $this->sanitizeColumns($data);
        $set = '';
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ', ');
        $sql = "UPDATE {$this->table} SET $set WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Filtra las claves de $data para que solo pasen nombres de columna
     * válidos (letras, dígitos y guion bajo). Elimina así cualquier intento
     * de inyección SQL a través de los nombres de columna interpolados en
     * create()/update(). Si no queda ninguna columna válida, lanza excepción.
     */
    private function sanitizeColumns(array $data): array {
        $safe = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                $safe[$key] = $value;
            }
        }
        if (empty($safe)) {
            throw new InvalidArgumentException('No se recibieron columnas válidas para la operación.');
        }
        return $safe;
    }
}