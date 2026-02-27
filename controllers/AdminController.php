<?php
require_once 'models/Juego.php';
require_once 'models/Consola.php';
require_once 'models/Categoria.php';

class AdminController {
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?controller=auth&action=login');
            exit;
        }
    }

    public function dashboard() {
        $juegoModel = new Juego();

        $busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' ? $_GET['busqueda'] : null;

        $itemsPerPage = 20;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        $juegos = $juegoModel->getAllPaginated($offset, $itemsPerPage, $busqueda);
        $totalJuegos = $juegoModel->countAll($busqueda);
        $totalPages = ceil($totalJuegos / $itemsPerPage);

        require_once 'views/layout/header.php';
        require_once 'views/admin/dashboard.php';
        require_once 'views/layout/footer.php';
    }

    public function add() {
        $consolaModel = new Consola();
        $categoriaModel = new Categoria();
        $consolas = $consolaModel->all();
        $categorias = $categoriaModel->all();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'titulo' => $_POST['titulo'],
                'descripcion' => $_POST['descripcion'],
                'consola_id' => $_POST['consola_id'],
                'categoria_id' => $_POST['categoria_id'],
                'region' => $_POST['region'],
                'fecha_lanzamiento' => $_POST['fecha_lanzamiento'] ?: null,
                'idiomas' => $_POST['idiomas'],
                'formato_imagen' => $_POST['formato_imagen'],
                'game_id_code' => $_POST['game_id_code'],
                'google_drive_file_id' => $_POST['google_drive_file_id'],
                'google_drive_view_link' => $_POST['google_drive_view_link'],
                'size_bytes' => $_POST['size_bytes'] ?: 0,
                'activo' => 1,
            ];

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen']);
                if ($imagenResult['success']) {
                    $data['imagen'] = $imagenResult['filename'];
                } else {
                    $error = $imagenResult['error'];
                }
            }

            if (!isset($error)) {
                $juegoModel = new Juego();
                if ($juegoModel->create($data)) {
                    header('Location: index.php?controller=admin&action=dashboard');
                    exit;
                } else {
                    $error = "Error al guardar el juego en la base de datos";
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/add.php';
        require_once 'views/layout/footer.php';
    }

    public function edit($id = null) {
        if (!$id) {
            die("Error: No se especificó el ID del juego a editar");
        }
        
        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);
        
        if (!$juego) {
            die("Error: Juego no encontrado con ID: " . $id);
        }

        $consolaModel = new Consola();
        $categoriaModel = new Categoria();
        $consolas = $consolaModel->all();
        $categorias = $categoriaModel->all();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'titulo' => $_POST['titulo'],
                'descripcion' => $_POST['descripcion'],
                'consola_id' => $_POST['consola_id'],
                'categoria_id' => $_POST['categoria_id'],
                'region' => $_POST['region'],
                'fecha_lanzamiento' => $_POST['fecha_lanzamiento'] ?: null,
                'idiomas' => $_POST['idiomas'],
                'formato_imagen' => $_POST['formato_imagen'],
                'game_id_code' => $_POST['game_id_code'],
                'google_drive_file_id' => $_POST['google_drive_file_id'],
                'google_drive_view_link' => $_POST['google_drive_view_link'],
                'size_bytes' => $_POST['size_bytes'] ?: 0,
                'activo' => isset($_POST['activo']) ? 1 : 0,
            ];

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen']);
                if ($imagenResult['success']) {
                    if ($juego['imagen'] && file_exists($juego['imagen'])) {
                        unlink($juego['imagen']);
                    }
                    $data['imagen'] = $imagenResult['filename'];
                } else {
                    $error = $imagenResult['error'];
                }
            }

            if (!isset($error)) {
                if ($juegoModel->update($id, $data)) {
                    header('Location: index.php?controller=admin&action=dashboard');
                    exit;
                } else {
                    $error = "Error al actualizar el juego";
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/edit.php';
        require_once 'views/layout/footer.php';
    }

    public function toggleActive($id) {
        if (!$id) {
            header('Location: index.php?controller=admin&action=dashboard');
            exit;
        }

        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);

        if ($juego) {
            $nuevoEstado = $juego['activo'] ? 0 : 1;
            $juegoModel->update($id, ['activo' => $nuevoEstado]);
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        header('Location: index.php?controller=admin&action=dashboard&page=' . $page);
        exit;
    }

    private function uploadImage($file) {
        $uploadDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'La imagen no puede ser mayor a 2MB'];
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true, 'filename' => 'public/uploads/' . $filename];
        } else {
            return ['success' => false, 'error' => 'Error al subir el archivo'];
        }
    }

    public function delete($id) {
        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);
        if ($juego && $juego['imagen'] && file_exists($juego['imagen'])) {
            unlink($juego['imagen']);
        }
        $juegoModel->delete($id);
        header('Location: index.php?controller=admin&action=dashboard');
        exit;
    }
}