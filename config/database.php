<?php
// config/database.php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 5432;
        $db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER');
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
        
        // DSN para PostgreSQL (Neon requiere SSL)
        $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            
            // Establecer codificación UTF-8
            $this->pdo->exec("SET NAMES 'UTF8'");
            
        } catch (PDOException $e) {
            // Log del error para depuración
            error_log("Error de conexión PostgreSQL: " . $e->getMessage());
            
            // Mensaje amigable para el usuario
            die("Error de conexión a la base de datos. Detalle técnico: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
    
    // Método para probar la conexión (útil para depuración)
    public static function testConnection() {
        try {
            $pdo = self::getInstance();
            $version = $pdo->query("SELECT version()")->fetch();
            return [
                'success' => true,
                'message' => 'Conexión exitosa a PostgreSQL',
                'version' => $version['version']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }
}