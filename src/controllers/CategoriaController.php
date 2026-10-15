<?php
// controllers/CategoriaController.php
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Juego.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../config/CsrfService.php';
require_once __DIR__ . '/../config/TraduccionService.php';

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

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/categorias/index.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    // ── Crear ─────────────────────────────────────────────────────────────
    public function add() {
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = trim($_POST['nombre']      ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if ($nombre === '') {
                $error = t('msg.categoria_nombre_obligatorio');
            } else {
                $categoriaModel = new Categoria();
                $ok = $categoriaModel->create([
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion ?: null,
                    'activo'      => 1,
                ]);

                if ($ok) {
                    $this->autoTraducirCategoria($categoriaModel->lastInsertId(), $nombre, $descripcion);
                    header('Location: /categoria/index?created=1');
                    exit;
                } else {
                    $error = t('msg.categoria_guardar_error');
                }
            }
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/categorias/add.php';
        require_once __DIR__ . '/../views/layout/footer.php';
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
                $error = t('msg.categoria_nombre_obligatorio');
            } else {
                $ok = $categoriaModel->update($id, [
                    'nombre'      => $nombre,
                    'descripcion' => $descripcion ?: null,
                    'activo'      => 1,
                ]);

                if ($ok) {
                    $this->autoTraducirCategoria((int) $id, $nombre, $descripcion);
                    header('Location: /categoria/index?updated=1');
                    exit;
                } else {
                    $error = t('msg.categoria_actualizar_error');
                }
            }
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/categorias/edit.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Auto-traduce una categoría al guardar (best-effort, no bloquea el flujo:
     * el registro ya está persistido). Sin proveedor configurado no hace nada.
     */
    private function autoTraducirCategoria(int $id, string $nombre, string $descripcion): void {
        if ($id <= 0 || $nombre === '') {
            return;
        }
        $campos = [
            'nombre'      => $nombre,
            'descripcion' => $descripcion,
        ];
        // Con TRANSLATE_MODE=async el trabajo se encola y el worker CLI lo
        // traduce en segundo plano (el admin no espera a las 11 llamadas).
        if (TraduccionService::modo() === 'async') {
            TraduccionService::encolar('categoria', $id, $campos);
            return;
        }
        TraduccionService::traducirEntidad('categoria', $id, $campos);
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
            echo json_encode(['ok' => false, 'error' => t('msg.id_no_especificado')]);
            exit;
        }

        $categoriaModel = new Categoria();
        $categoria      = $categoriaModel->find($id);

        if (!$categoria) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => t('msg.categoria_no_encontrada')]);
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
