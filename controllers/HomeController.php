<?php
// controllers/HomeController.php
require_once 'models/Juego.php';
require_once 'models/Consola.php';
require_once 'models/Categoria.php';

class HomeController {
    
    public function index() {
        $juegoModel = new Juego();
        $consolaModel = new Consola();
        $categoriaModel = new Categoria();

        // Obtener filtros
        $filters = [];
        if (isset($_GET['consola']) && $_GET['consola'] !== '') {
            $filters['consola'] = $_GET['consola'];
        }
        if (isset($_GET['categoria']) && $_GET['categoria'] !== '') {
            $filters['categoria'] = $_GET['categoria'];
        }
        if (isset($_GET['region']) && $_GET['region'] !== '') {
            $filters['region'] = $_GET['region'];
        }

        // Configuración de paginación
        $itemsPerPage = 20; // 4 columnas × 5 filas
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        
        $offset = ($currentPage - 1) * $itemsPerPage;
        
        // Obtener juegos paginados
        $juegos = $juegoModel->getWithRelationsPaginated($filters, $offset, $itemsPerPage);
        
        // Obtener total de juegos para la paginación
        $totalJuegos = $juegoModel->countWithFilters($filters);
        $totalPages = ceil($totalJuegos / $itemsPerPage);
        
        // DEBUG - Registrar valores para verificar
        error_log("=== PAGINACIÓN ===");
        error_log("totalJuegos: " . $totalJuegos);
        error_log("itemsPerPage: " . $itemsPerPage);
        error_log("totalPages: " . $totalPages);
        error_log("currentPage: " . $currentPage);
        error_log("offset: " . $offset);
        error_log("juegos encontrados: " . count($juegos));
        
        // Obtener datos para filtros
        $consolas = $consolaModel->all();
        $categorias = $categoriaModel->all();

        // PASAR TODAS LAS VARIABLES A LA VISTA
        require_once 'views/layout/header.php';
        require_once 'views/home/index.php';
        require_once 'views/layout/footer.php';
    }

    public function download() {
        $fileId = $_GET['file_id'] ?? null;
        
        if (!$fileId) {
            die("Error: No se especificó el archivo a descargar");
        }
        
        $juegoModel = new Juego();
        $juego = $juegoModel->findByFileId($fileId);
        
        if (!$juego) {
            die("Error: El archivo solicitado no existe en nuestra base de datos");
        }
        
        // Incrementar contador de descargas
        $juegoModel->incrementDownloads($juego['id']);
        
        $downloadLink = "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t";
        header("Location: " . $downloadLink);
        exit;
    }
}