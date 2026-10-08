<?php
// controllers/ErrorsController.php
// Maneja páginas de error HTTP: 404, 403, 500

class ErrorsController {

    public function notFound() {
        http_response_code(404);
        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/errors/404.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    public function forbidden() {
        http_response_code(403);
        $errorCode  = 403;
        $errorTitle = 'Acceso denegado';
        $errorMsg   = 'No tienes permiso para acceder a esta página.';
        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/errors/generic.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    public function serverError() {
        http_response_code(500);
        $errorCode  = 500;
        $errorTitle = 'Error interno del servidor';
        $errorMsg   = 'Algo salió mal en el servidor. Inténtalo de nuevo en unos minutos.';
        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/errors/generic.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }
}
