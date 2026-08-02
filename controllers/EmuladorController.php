<?php
// controllers/EmuladorController.php
require_once 'models/Emulador.php';
require_once 'models/Consola.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

class EmuladorController {

    public function __construct() {
        AuthMiddleware::requireAuth();
    }

    // ── Listado (con búsqueda y filtro por estado, igual que consolas) ────
    public function index() {
        $emuladorModel = new Emulador();

        $busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== ''
            ? trim($_GET['busqueda']) : null;

        $itemsPerPage = 20;
        $currentPage  = max(1, (int)($_GET['page'] ?? 1));
        $offset       = ($currentPage - 1) * $itemsPerPage;

        $activo   = isset($_GET['activo']) && $_GET['activo'] !== '' ? $_GET['activo'] : null;
        $consolas = $emuladorModel->getConsolasPaginated($busqueda, $offset, $itemsPerPage, $activo);
        $total        = $emuladorModel->countConsolas($busqueda);
        $totalActivas = $emuladorModel->countConsolas($busqueda, '1');
        $totalPages   = (int)ceil($emuladorModel->countConsolas($busqueda, $activo) / $itemsPerPage);

        // Emuladores de las consolas visibles en esta página, agrupados por consola
        $emuladoresPorConsola = [];
        $emuladores = $emuladorModel->getByConsolaIds(array_map('intval', array_column($consolas, 'id')));
        foreach ($emuladores as $e) {
            $cid = (int) $e['consola_id'];
            if (!isset($emuladoresPorConsola[$cid])) {
                $emuladoresPorConsola[$cid] = ['principal' => null, 'alterno' => null];
            }
            $dato = [
                'nombre'      => $e['nombre'],
                'plataformas' => array_values(array_filter(array_map('trim', explode(',', (string) $e['plataformas'])))),
                'url'         => $e['url'],
            ];
            if (!empty($e['es_alterno'])) {
                $emuladoresPorConsola[$cid]['alterno'] = $dato;
            } else {
                $emuladoresPorConsola[$cid]['principal'] = $dato;
            }
        }

        // Filas finales: una por consola, con su estado y sus emuladores
        $filas = [];
        foreach ($consolas as $c) {
            $cid = (int) $c['id'];
            $filas[] = [
                'id'             => $cid,
                'consola_nombre' => $c['consola_nombre'],
                'activo'         => (bool) $c['activo'],
                'principal'      => $emuladoresPorConsola[$cid]['principal'] ?? null,
                'alterno'        => $emuladoresPorConsola[$cid]['alterno'] ?? null,
            ];
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/emuladores/index.php';
        require_once 'views/layout/footer.php';
    }

    // ── Editar emuladores de una consola ──────────────────────────────────
    public function edit($id = null) {
        if (!$id) {
            header('Location: index.php?controller=emulador&action=index');
            exit;
        }

        $consolaModel = new Consola();
        $consola      = $consolaModel->find($id);

        if (!$consola) {
            header('Location: index.php?controller=emulador&action=index');
            exit;
        }

        $emuladorModel = new Emulador();
        $registros     = $emuladorModel->getByConsola((int) $id);

        // Emuladores ya guardados (para prellenar el formulario)
        $actual = ['principal' => null, 'alterno' => null];
        foreach ($registros as $r) {
            $key = !empty($r['es_alterno']) ? 'alterno' : 'principal';
            $actual[$key] = [
                'nombre'      => $r['nombre'],
                'plataformas' => array_values(array_filter(array_map('trim', explode(',', (string) $r['plataformas'])))),
                'url'         => $r['url'],
            ];
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $principal = $this->parseEmuladorPost('principal');
            $alterno   = $this->parseEmuladorPost('alterno');

            if ($principal !== null) {
                $error = $this->validarEmulador($principal);
            }
            if ($error === null && $alterno !== null) {
                $error = $this->validarEmulador($alterno);
            }

            if ($error === null) {
                $ok = $emuladorModel->replaceForConsola((int) $id, $principal, $alterno);
                if ($ok) {
                    header('Location: index.php?controller=emulador&action=index&updated=1');
                    exit;
                }
                $error = 'Error al guardar los emuladores. Revisa los datos e inténtalo de nuevo.';
            }

            // Si hay error, mantener lo que escribió el usuario en el formulario
            if ($principal !== null) $actual['principal'] = $principal;
            if ($alterno   !== null) $actual['alterno']   = $alterno;
        }

        require_once 'views/layout/header.php';
        require_once 'views/admin/emuladores/edit.php';
        require_once 'views/layout/footer.php';
    }

    // ── Toggle activo (AJAX) — igual que consolas/categorías ──────────────
    public function toggleActiveAjax($id = null) {
        header('Content-Type: application/json; charset=utf-8');

        if (!$id) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID no especificado']);
            exit;
        }

        $emuladorModel = new Emulador();
        $nuevoEstado   = $emuladorModel->toggleActivoByConsola((int) $id);

        if ($nuevoEstado === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Esta consola no tiene emuladores configurados']);
            exit;
        }

        $consolaModel = new Consola();
        $consola      = $consolaModel->find($id);

        echo json_encode([
            'ok'     => true,
            'activo' => $nuevoEstado ? 1 : 0,
            'nombre' => $consola['nombre'] ?? '',
        ]);
        exit;
    }

    // ── Parsear el POST de un emulador (principal o alterno) ──────────────
    private function parseEmuladorPost(string $prefijo): ?array {
        $nombre = trim($_POST[$prefijo . '_nombre'] ?? '');
        $url    = trim($_POST[$prefijo . '_url'] ?? '');

        // Campos vacíos → no se guarda ese emulador
        if ($nombre === '' && $url === '') {
            return null;
        }

        $plataformas = $_POST[$prefijo . '_plataformas'] ?? [];
        if (!is_array($plataformas)) {
            $plataformas = [];
        }
        $plataformas = array_values(array_filter(array_map('trim', $plataformas), fn($p) => $p !== ''));

        return [
            'nombre'      => $nombre,
            'plataformas' => $plataformas,
            'url'         => $url,
        ];
    }

    // ── Validar un emulador antes de guardarlo ────────────────────────────
    private function validarEmulador(array $emulador): ?string {
        if ($emulador['nombre'] === '') {
            return 'El nombre del emulador es obligatorio.';
        }
        if (mb_strlen($emulador['nombre']) > 100) {
            return 'El nombre del emulador no puede superar los 100 caracteres.';
        }
        if (empty($emulador['plataformas'])) {
            return 'Selecciona al menos una plataforma (PC o Android).';
        }
        if ($emulador['url'] === '' || !filter_var($emulador['url'], FILTER_VALIDATE_URL)) {
            return 'La URL del emulador debe ser una dirección válida (incluye http:// o https://).';
        }
        if (mb_strlen($emulador['url']) > 300) {
            return 'La URL no puede superar los 300 caracteres.';
        }
        return null;
    }
}
