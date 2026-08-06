<?php
// controllers/CategoriaController.php
require_once 'models/Categoria.php';
require_once 'models/Juego.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../config/CsrfService.php';

class CategoriaController {

    public function __construct() {
        AuthMiddleware::requireAdmin();
    }

    // ── Listado ───────────────────────────────────────────────────────────
    public function index() {
        $categoriaModel = new Categoria();

        $busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== ''
            ? trim($_GET['busqueda']) : null;

        $itemsPerPage = 20;
        $currentPage  = max(1, (int)($_GET['page'] ?? 1));
        $offset       = ($currentPage - 1) * $itemsPerPage;

        $activo     = isset($_GET['activo']) && $_GET['activo'] !== '' ? $_GET['activo'] : null;
        $categorias = $categoriaModel->getAllPaginated($busqueda, $offset, $itemsPerPage, $activo);
        $total        = $categoriaModel->countAll($busqueda);
        $totalActivas = $categoriaModel->countActivas();
        $totalPages   = (int)ceil($categoriaModel->countAll($busqueda, $activo) / $itemsPerPage);

        require_once 'views/layout/header.php';
        require_once 'views/admin/categorias/index.php';
        require_once 'views/layout/footer.php';
    }

    // ── Crear ─────────────────────────────────────────────────────────────
    public function add() {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if ($nombre === '') {
                $error = 'El nombre de la categoría es obligatorio.';
            } else {
                $categoriaModel = new Categoria();
                $ok = $categoriaModel->create([
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion ?: null,
                    'activo'      => 1,
                ]);

                if ($ok) {
                    header('Location: /categoria/index?created=1');
                    exit;
                } else {
                    $error = 'Error al guardar la categoría. Es posible que el nombre ya exista.';
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/categorias/add.php';
        require_once 'views/layout/footer.php';
    }

    // ── Editar ────────────────────────────────────────────────────────────
    public function edit($id = null) {
        if (!$id) {
            header('Location: /categoria/index');
            exit;
        }

        $categoriaModel = new Categoria();
        $categoria      = $categoriaModel->find($id);

        if (!$categoria) {
            header('Location: /categoria/index');
            exit;
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if ($nombre === '') {
                $error = 'El nombre de la categoría es obligatorio.';
            } else {
                $ok = $categoriaModel->update($id, [
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion ?: null,
                    'activo'      => 1,
                ]);

                if ($ok) {
                    header('Location: /categoria/index?updated=1');
                    exit;
                } else {
                    $error = 'Error al actualizar la categoría.';
                }
            }
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/categorias/edit.php';
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
            header('Location: /categoria/index');
            exit;
        }

        // Verificar si hay juegos asociados
        $juegoModel = new Juego();
        $filters    = ['categoria' => $id, 'activo' => ''];
        $total      = $juegoModel->countAllFiltered($filters);

        if ($total > 0) {
            header('Location: /categoria/index?error=has_games&count=' . $total);
            exit;
        }

        $categoriaModel = new Categoria();
        $categoriaModel->delete($id);

        header('Location: /categoria/index?deleted=1');
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

        $categoriaModel = new Categoria();
        $categoria      = $categoriaModel->find($id);

        if (!$categoria) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Categoría no encontrada']);
            exit;
        }

        $nuevoEstado = $categoria['activo'] ? 0 : 1;
        $ok          = $categoriaModel->update($id, ['activo' => $nuevoEstado]);

        echo json_encode([
            'ok'     => (bool)$ok,
            'activo' => $nuevoEstado,
            'nombre' => $categoria['nombre'],
        ]);
        exit;
    }
}
