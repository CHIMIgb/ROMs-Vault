<?php
// controllers/ConsolaController.php
require_once 'models/Consola.php';
require_once 'models/Juego.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../config/CsrfService.php';

class ConsolaController {

    public function __construct() {
        AuthMiddleware::requireAdmin();
    }

    // ── Listado ───────────────────────────────────────────────────────────
    public function index() {
        $consolaModel = new Consola();

        $busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== ''
            ? trim($_GET['busqueda']) : null;

        $itemsPerPage = 20;
        $currentPage  = max(1, (int)($_GET['page'] ?? 1));
        $offset       = ($currentPage - 1) * $itemsPerPage;

        $activo   = isset($_GET['activo']) && $_GET['activo'] !== '' ? $_GET['activo'] : null;
        $consolas = $consolaModel->getAllPaginated($busqueda, $offset, $itemsPerPage, $activo);
        $total        = $consolaModel->countAll($busqueda);
        $totalActivas = $consolaModel->countActivas();
        $totalPages   = (int)ceil($consolaModel->countAll($busqueda, $activo) / $itemsPerPage);

        require_once 'views/layout/header.php';
        require_once 'views/admin/consolas/index.php';
        require_once 'views/layout/footer.php';
    }

    // ── Crear ─────────────────────────────────────────────────────────────
    public function add() {
        $error   = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $fabricante  = trim($_POST['fabricante']  ?? '');
            $emulacionOnline = isset($_POST['emulacion_online']) ? 1 : 0;

            if ($nombre === '') {
                $error = 'El nombre de la consola es obligatorio.';
            } else {
                $consolaModel = new Consola();
                $ok = $consolaModel->create([
                    'nombre'           => $nombre,
                    'descripcion'      => $descripcion ?: null,
                    'fabricante'       => $fabricante  ?: null,
                    'activo'           => 1,
                    'emulacion_online' => $emulacionOnline,
                ]);

                if ($ok) {
                    header('Location: index.php?controller=consola&action=index&created=1');
                    exit;
                } else {
                    $error = 'Error al guardar la consola. Es posible que el nombre ya exista.';
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/consolas/add.php';
        require_once 'views/layout/footer.php';
    }

    // ── Editar ────────────────────────────────────────────────────────────
    public function edit($id = null) {
        if (!$id) {
            header('Location: index.php?controller=consola&action=index');
            exit;
        }

        $consolaModel = new Consola();
        $consola      = $consolaModel->find($id);

        if (!$consola) {
            header('Location: index.php?controller=consola&action=index');
            exit;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $fabricante  = trim($_POST['fabricante']  ?? '');
            $emulacionOnline = isset($_POST['emulacion_online']) ? 1 : 0;

            if ($nombre === '') {
                $error = 'El nombre de la consola es obligatorio.';
            } else {
                $ok = $consolaModel->update($id, [
                    'nombre'           => $nombre,
                    'descripcion'      => $descripcion ?: null,
                    'fabricante'       => $fabricante  ?: null,
                    'activo'           => 1,
                    'emulacion_online' => $emulacionOnline,
                ]);

                if ($ok) {
                    header('Location: index.php?controller=consola&action=index&updated=1');
                    exit;
                } else {
                    $error = 'Error al actualizar la consola. El nombre puede estar duplicado.';
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/consolas/edit.php';
        require_once 'views/layout/footer.php';
    }

    // ── Eliminar ──────────────────────────────────────────────────────────
    public function delete($id = null) {
        // Mutador — exigir token CSRF (POST o GET con token)
        if (!CsrfService::verify()) {
            CsrfService::deny();
        }

        // El id puede venir del router (GET) o del body POST
        if (!$id) {
            $id = $_POST['id'] ?? null;
        }

        if (!$id) {
            header('Location: index.php?controller=consola&action=index');
            exit;
        }

        // Verificar si hay juegos asociados
        $juegoModel = new Juego();
        $filters    = ['consola' => $id, 'activo' => ''];   // todos, activos e inactivos
        $total      = $juegoModel->countAllFiltered($filters);

        if ($total > 0) {
            header('Location: index.php?controller=consola&action=index&error=has_games&count=' . $total);
            exit;
        }

        $consolaModel = new Consola();
        $consolaModel->delete($id);

        header('Location: index.php?controller=consola&action=index&deleted=1');
        exit;
    }

    // ── Toggle activo (AJAX) ──────────────────────────────────────────────
    public function toggleActiveAjax($id = null) {
        header('Content-Type: application/json; charset=utf-8');

        // AJAX mutador — exigir token CSRF por header
        if (!CsrfService::verifyAjax()) {
            CsrfService::deny();
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado']);
            exit;
        }

        $consolaModel = new Consola();
        $consola      = $consolaModel->find($id);

        if (!$consola) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Consola no encontrada']);
            exit;
        }

        $nuevoEstado = $consola['activo'] ? 0 : 1;
        $ok          = $consolaModel->update($id, ['activo' => $nuevoEstado]);

        echo json_encode([
            'ok'     => (bool)$ok,
            'activo' => $nuevoEstado,
            'nombre' => $consola['nombre'],
        ]);
        exit;
    }

    // ── Toggle emulación online (AJAX) ────────────────────────────────────
    public function toggleEmulacionAjax($id = null) {
        header('Content-Type: application/json; charset=utf-8');

        // AJAX mutador — exigir token CSRF por header
        if (!CsrfService::verifyAjax()) {
            CsrfService::deny();
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado']);
            exit;
        }

        $consolaModel = new Consola();
        $consola      = $consolaModel->find($id);

        if (!$consola) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Consola no encontrada']);
            exit;
        }

        $nuevoEstado = $consola['emulacion_online'] ? 0 : 1;
        $ok          = $consolaModel->update($id, ['emulacion_online' => $nuevoEstado]);

        echo json_encode([
            'ok'              => (bool)$ok,
            'emulacion_online' => $nuevoEstado,
            'nombre'          => $consola['nombre'],
        ]);
        exit;
    }
}
