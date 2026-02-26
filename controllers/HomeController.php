<?php
require_once 'models/Juego.php';
require_once 'models/Consola.php';
require_once 'models/Categoria.php';

class HomeController {
    public function index() {
        $juegoModel = new Juego();
        $consolaModel = new Consola();
        $categoriaModel = new Categoria();

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

        $juegos = $juegoModel->getWithRelations($filters);
        $consolas = $consolaModel->all();
        $categorias = $categoriaModel->all();
        $regiones = ['PAL', 'NTSC', 'NTSC-J']; // O podrías obtenerlas de la BD

        require_once 'views/layout/header.php';
        require_once 'views/home/index.php';
        require_once 'views/layout/footer.php';
    }

    public function download() {
        // Obtener el file_id de la URL
        $fileId = $_GET['file_id'] ?? null;
        
        if (!$fileId) {
            die("Error: No se especificó el archivo a descargar");
        }
        
        // Validar que el file_id existe en la base de datos (opcional pero recomendado)
        $juegoModel = new Juego();
        $juego = $juegoModel->findByFileId($fileId);
        
        if (!$juego) {
            die("Error: El archivo solicitado no existe en nuestra base de datos");
        }
        
        // Incrementar contador de descargas (opcional)
        $juegoModel->incrementDownloads($juego['id']);
        
        // Construir enlace de descarga directa de Google Drive
        // El parámetro 'confirm=t' ayuda a evitar la página de advertencia de Google
        $downloadLink = "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t";
        
        // Redirigir a Google Drive
        header("Location: " . $downloadLink);
        exit;
    }
}