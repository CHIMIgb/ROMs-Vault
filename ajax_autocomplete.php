<?php
// ajax_autocomplete.php — Sugerencias de búsqueda en tiempo real
// Endpoint público — no requiere autenticación
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$term = trim($_GET['q'] ?? '');

// Mínimo 2 caracteres para no sobrecargar la BD
if (mb_strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

// Máximo 80 caracteres por seguridad
$term = mb_substr($term, 0, 80);

require_once 'models/Juego.php';
require_once 'config/UrlSigner.php';

try {
    $juegoModel  = new Juego();
    $resultados  = $juegoModel->autocomplete($term, 8);

    $output = array_map(function ($j) {
        return [
            'titulo'        => $j['titulo'],
            'consola'       => $j['consola_nombre'] ?? '',
            'imagen'        => $j['imagen'] ?? null,
            'file_id'       => $j['google_drive_file_id'],
            'emulacion_online' => (bool)($j['consola_emulacion_online'] ?? true),
            'play_url'      => '/home/play?file_id='     . urlencode($j['google_drive_file_id']),
            'download_url'  => UrlSigner::downloadUrl($j['google_drive_file_id']),
        ];
    }, $resultados);

    echo json_encode($output, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
