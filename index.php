<?php
// index.php
session_start();

$controller = $_GET['controller'] ?? 'home';
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null; // Capturar el ID de la URL

$controllerFile = "controllers/" . ucfirst($controller) . "Controller.php";

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    $controllerClass = ucfirst($controller) . "Controller";
    $controllerInstance = new $controllerClass();
    
    if (is_callable([$controllerInstance, $action])) {
        // Pasar el ID si existe
        if ($id) {
            $controllerInstance->$action($id);
        } else {
            $controllerInstance->$action();
        }
    } else {
        die("Acción no encontrada: " . $action);
    }
} else {
    die("Controlador no encontrado: " . $controller);
}