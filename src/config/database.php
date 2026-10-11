<?php
// config/database.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
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
            // Log del error a nivel general (no expuesto al usuario). Se omite
            // en el entorno de test (ROMV_TESTING) para no ensuciar PHPUnit.
            if (!defined('ROMV_TESTING')) {
                error_log("Error de conexión PostgreSQL: " . $e->getMessage());
            }

            // No se hace die() aquí: el comportamiento decide getInstance()
            // (fail-hard, 503 genérico) o tryGetInstance() (fail-open, null).
            $this->pdo = null;
        }
    }

    public static function getInstance() {
        $pdo = self::tryGetInstance();
        if ($pdo === null) {
            // Comportamiento histórico: mensaje genérico 503, nunca detalles técnicos
            http_response_code(503);
            die("No se pudo conectar con la base de datos. Inténtalo de nuevo en unos minutos.");
        }
        return $pdo;
    }

    /**
     * Devuelve el PDO o null si la BD no está disponible.
     * Fail-open: pensado para flujos que NO deben morir si la BD falla
     * (p. ej. el LoggerService, que cae al archivo como respaldo).
     * A diferencia de getInstance(), nunca emite die()/503.
     *
     * Nota: solo se cachea una conexión EXITOSA. Si la primera conexión
     * falla (p. ej. en la suite Unit) y más tarde las variables de entorno
     * apuntan a una BD accesible (suite Integration), se reconecta.
     */
    public static function tryGetInstance() {
        if (self::$instance !== null && self::$instance->pdo !== null) {
            return self::$instance->pdo;
        }
        $db = new Database();
        if ($db->pdo !== null) {
            self::$instance = $db;
        }
        return $db->pdo;
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