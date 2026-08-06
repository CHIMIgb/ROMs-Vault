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

        // Modo SSL del DSN. Por defecto 'require' (Neon en producción). Los tests
        // de integración usan un PostgreSQL local sin SSL → DB_SSLMODE=disable.
        $sslmode = $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?: 'require';

        // DSN para PostgreSQL (Neon requiere SSL)
        $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$sslmode";
        
        // Parche para XAMPP/Windows local: Si el cliente de PostgreSQL es antiguo (no soporta SNI)
        // Neon requiere que pasemos explícitamente el ID del endpoint en el parámetro 'options'.
        if (strpos($host, 'neon.tech') !== false) {
            $endpointId = explode('.', $host)[0];
            $dsn .= ";options='endpoint=$endpointId'";
        }
        
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

            // Mensaje genérico al usuario — nunca exponer detalles técnicos
            http_response_code(503);
            die("No se pudo conectar con la base de datos. Inténtalo de nuevo en unos minutos.");
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