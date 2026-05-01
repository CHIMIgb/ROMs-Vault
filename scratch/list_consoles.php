<?php
require_once 'config/database.php';
$pdo = Database::getInstance();
$stmt = $pdo->query('SELECT nombre FROM consolas');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
