<?php
// test_download.php
require_once 'config/database.php';
require_once 'models/Juego.php';

echo "<h1>Test de Descarga</h1>";

// Probar conexión a DB
try {
    $pdo = Database::getInstance();
    echo "✅ Conexión a DB exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}

// Probar búsqueda de juego
$juegoModel = new Juego();
$juego = $juegoModel->find(1);

echo "<h2>Buscando juego ID 1:</h2>";
if ($juego) {
    echo "✅ Juego encontrado:<br>";
    echo "<pre>";
    print_r($juego);
    echo "</pre>";
    
    echo "<br>🔗 File ID: " . $juego['google_drive_file_id'] . "<br>";
    echo "📥 Link de descarga: https://drive.google.com/uc?export=download&id=" . $juego['google_drive_file_id'] . "&confirm=t<br>";
    
    // Link para probar
    echo '<br><a href="https://drive.google.com/uc?export=download&id=' . $juego['google_drive_file_id'] . '&confirm=t" target="_blank">🔗 Probar descarga directa</a>';
} else {
    echo "❌ Juego NO encontrado<br>";
    
    // Verificar qué juegos hay
    echo "<h3>Juegos disponibles:</h3>";
    $stmt = $pdo->query("SELECT id, titulo, google_drive_file_id FROM juegos");
    $juegos = $stmt->fetchAll();
    if ($juegos) {
        echo "<pre>";
        print_r($juegos);
        echo "</pre>";
    } else {
        echo "No hay juegos en la base de datos";
    }
}