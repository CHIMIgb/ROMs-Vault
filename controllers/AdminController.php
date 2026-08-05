<?php
require_once 'models/Juego.php';
require_once 'models/Consola.php';
require_once 'models/Categoria.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../config/CsrfService.php';

class AdminController {
    // Máximo de capturas por juego (el carrusel ya replica la portada si no hay capturas)
    private const MAX_CAPTURAS = 7;

    public function __construct() {
        AuthMiddleware::requireAdmin();
    }

    public function dashboard() {
        $juegoModel = new Juego();

        // Recoger filtros extendidos
        $filters = [];
        if (isset($_GET['busqueda'])  && $_GET['busqueda']  !== '') $filters['busqueda']  = $_GET['busqueda'];
        if (isset($_GET['consola'])   && $_GET['consola']   !== '') $filters['consola']   = $_GET['consola'];
        if (isset($_GET['categoria']) && $_GET['categoria'] !== '') $filters['categoria'] = $_GET['categoria'];
        if (isset($_GET['region'])    && $_GET['region']    !== '') $filters['region']    = $_GET['region'];
        // activo: '' todos, '1' activos, '0' inactivos
        if (isset($_GET['activo']) && $_GET['activo'] !== '') $filters['activo'] = $_GET['activo'];

        $itemsPerPage = 20;
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentPage < 1) $currentPage = 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        $juegos      = $juegoModel->getAllPaginatedFiltered($filters, $offset, $itemsPerPage);
        $totalJuegos = $juegoModel->countAllFiltered($filters);
        $totalPages  = (int)ceil($totalJuegos / $itemsPerPage);

        // Estadísticas globales reales (independientes de filtros/paginación)
        $stats       = $juegoModel->getGlobalStats();
        $topDescargas = $juegoModel->getTopByDownloads(5);
        $topJugados   = $juegoModel->getTopByPlays(5);

        // Para los selects de filtro en la vista
        require_once 'models/Consola.php';
        require_once 'models/Categoria.php';
        $consolas   = (new Consola())->all();
        $categorias = (new Categoria())->all();

        require_once 'views/layout/header.php';
        require_once 'views/admin/dashboard.php';
        require_once 'views/layout/footer.php';
    }

    public function add() {
        $consolaModel = new Consola();
        $categoriaModel = new Categoria();
        $consolas = $consolaModel->allActivas();
        $categorias = $categoriaModel->allActivas();

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

            $consolaNombre = $this->obtenerConsolaNombre((int)$data['consola_id']);

            // ── Carpeta del juego: public/uploads/{consola}/{juego}/ ──
            // Todas las imágenes (portada + capturas) van en esta misma carpeta.
            $carpetaJuego = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);

            // ── Portada (un solo archivo) ──
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen'], $carpetaJuego, 'portada');
                if ($imagenResult['success']) {
                    $data['imagen'] = $imagenResult['filename'];
                } else {
                    $error = $imagenResult['error'];
                }
            }

            // ── Capturas (múltiples archivos) ──
            if (!isset($error) && isset($_FILES['capturas'])) {
                $rutas = [];
                foreach ($_FILES['capturas']['name'] as $i => $nombreArchivo) {
                    if ($_FILES['capturas']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    if (count($rutas) >= self::MAX_CAPTURAS) {
                        break;
                    }
                    $file = [
                        'name'     => $nombreArchivo,
                        'type'     => $_FILES['capturas']['type'][$i],
                        'tmp_name' => $_FILES['capturas']['tmp_name'][$i],
                        'error'    => $_FILES['capturas']['error'][$i],
                        'size'     => $_FILES['capturas']['size'][$i],
                    ];
                    $capResult = $this->uploadImage($file, $carpetaJuego, 'captura-' . (count($rutas) + 1));
                    if (!$capResult['success']) {
                        $error = $capResult['error'];
                        break;
                    }
                    $rutas[] = $capResult['filename'];
                }
                if (!isset($error) && $rutas) {
                    $data['capturas'] = json_encode($rutas);
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
        $consolas = $consolaModel->allActivas();
        $categorias = $categoriaModel->allActivas();

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
                'activo' => $juego['activo'] ? 1 : 0,
            ];

            $consolaAnterior = (int)$juego['consola_id'];
            $tituloAnterior  = $juego['titulo'];
            $consolaNuevaId  = (int)$data['consola_id'];
            $cambioDestino   = $consolaNuevaId !== $consolaAnterior || $data['titulo'] !== $tituloAnterior;

            $consolaNombre = $this->obtenerConsolaNombre($consolaNuevaId);
            $carpetaVieja  = $this->carpetaJuegoAbsoluta($juego);
            $carpetaDestino = $carpetaVieja;

            // ── Si cambió consola/título: mover la carpeta a su nueva ubicación ──
            if ($cambioDestino) {
                $carpetaDestino = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);
                if ($carpetaVieja && is_dir($carpetaVieja)) {
                    $this->moverContenido($carpetaVieja, $carpetaDestino);
                    @rmdir($carpetaVieja);
                    // Actualizar las rutas viejas → nuevas para operar sobre la nueva ubicación
                    $baseVieja = 'public/uploads/' . basename(dirname($carpetaVieja)) . '/' . basename($carpetaVieja);
                    $baseNueva = 'public/uploads/' . basename(dirname($carpetaDestino)) . '/' . basename($carpetaDestino);
                    if (!empty($juego['imagen'])) {
                        $juego['imagen'] = str_replace($baseVieja, $baseNueva, $juego['imagen']);
                    }
                    if (!empty($juego['capturas'])) {
                        $nuevasRutas = [];
                        foreach (Juego::parseCapturas($juego['capturas']) as $cap) {
                            $nuevasRutas[] = str_replace($baseVieja, $baseNueva, $cap);
                        }
                        $juego['capturas'] = json_encode($nuevasRutas);
                    }
                    // Traducir también las rutas marcadas para eliminar (el formulario envió las viejas)
                    if (!empty($_POST['eliminar_capturas']) && is_array($_POST['eliminar_capturas'])) {
                        $_POST['eliminar_capturas'] = array_map(function ($r) use ($baseVieja, $baseNueva) {
                            return str_replace($baseVieja, $baseNueva, (string)$r);
                        }, $_POST['eliminar_capturas']);
                    }
                }
            } elseif (!$carpetaDestino || !is_dir($carpetaDestino)) {
                // Estructura antigua (portada en la raíz de uploads): crear carpeta nueva
                $carpetaDestino = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);
            }

            // ── Portada (un solo archivo) ──
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen'], $carpetaDestino, 'portada');
                if ($imagenResult['success']) {
                    // Eliminar la portada anterior si es un archivo distinto
                    if ($juego['imagen'] && file_exists($juego['imagen']) && $juego['imagen'] !== $imagenResult['filename']) {
                        @unlink($juego['imagen']);
                    }
                    $data['imagen'] = $imagenResult['filename'];
                } else {
                    $error = $imagenResult['error'];
                }
            } else {
                $data['imagen'] = $juego['imagen'] ?? null;
            }

            // ── Capturas: conservar las actuales, eliminar las marcadas y añadir las nuevas ──
            $capturasActuales = Juego::parseCapturas($juego['capturas'] ?? null);

            // Rutas marcadas explícitamente para eliminar (botones ✕ del formulario)
            $aEliminar = [];
            if (!empty($_POST['eliminar_capturas']) && is_array($_POST['eliminar_capturas'])) {
                $aEliminar = array_values(array_filter($_POST['eliminar_capturas'], 'is_string'));
            }

            // Filtro las actuales que NO se marcaron; borro los archivos de las marcadas
            $restantes = [];
            foreach ($capturasActuales as $ruta) {
                if (in_array($ruta, $aEliminar, true)) {
                    if (file_exists($ruta)) {
                        @unlink($ruta);
                    }
                } else {
                    $restantes[] = $ruta;
                }
            }

            // Añado las nuevas capturas (sin perder las existentes)
            $nuevas = [];
            if (!isset($error) && isset($_FILES['capturas'])) {
                foreach ($_FILES['capturas']['name'] as $i => $nombreArchivo) {
                    if ($_FILES['capturas']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    if (count($restantes) + count($nuevas) >= self::MAX_CAPTURAS) {
                        break;
                    }
                    $file = [
                        'name'     => $nombreArchivo,
                        'type'     => $_FILES['capturas']['type'][$i],
                        'tmp_name' => $_FILES['capturas']['tmp_name'][$i],
                        'error'    => $_FILES['capturas']['error'][$i],
                        'size'     => $_FILES['capturas']['size'][$i],
                    ];
                    $capResult = $this->uploadImage($file, $carpetaDestino, 'captura-' . (count($restantes) + count($nuevas) + 1));
                    if (!$capResult['success']) {
                        $error = $capResult['error'];
                        break;
                    }
                    $nuevas[] = $capResult['filename'];
                }
            }

            $capturasFinales = array_merge($restantes, $nuevas);
            $data['capturas'] = json_encode($capturasFinales);

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
        // Mutador vía GET — exigir token CSRF
        if (!CsrfService::verify()) {
            CsrfService::deny();
        }

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

    // Versión AJAX — devuelve JSON, no recarga la página
    public function toggleActiveAjax($id) {
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

        $juegoModel = new Juego();
        $juego      = $juegoModel->find($id);

        if (!$juego) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Juego no encontrado']);
            exit;
        }

        $nuevoEstado = $juego['activo'] ? 0 : 1;
        $ok          = $juegoModel->update($id, ['activo' => $nuevoEstado]);

        echo json_encode([
            'ok'       => (bool)$ok,
            'activo'   => $nuevoEstado,
            'titulo'   => $juego['titulo'],
        ]);
        exit;
    }

    /**
     * Sube y convierte una imagen a WebP dentro de la carpeta del juego.
     *
     * La carpeta destino ya viene resuelta (carpetaDestinoJuego() o la
     * carpeta existente del juego); NO se vuelve a resolver aquí, para
     * que portada y capturas compartan la misma carpeta.
     *
     * @param array  $file       Archivo del formulario ($_FILES['...'])
     * @param string $carpeta    Ruta absoluta de la carpeta del juego
     * @param string $nombreBase Nombre base del archivo: 'portada' o 'captura-N'
     */
    private function uploadImage($file, string $carpeta, string $nombreBase) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'La imagen no puede ser mayor a 2MB'];
        }

        // Verificar si la librería GD está activa en el servidor
        if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
            return ['success' => false, 'error' => 'La extensión GD de PHP no está activada en este servidor. Habilita "extension=gd" en tu archivo php.ini.'];
        }

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        // Cargar imagen en memoria según formato original
        $image = null;
        switch ($file['type']) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file['tmp_name']);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($file['tmp_name']);
                break;
        }

        if (!$image) {
            return ['success' => false, 'error' => 'Error al procesar la imagen (formato no válido o corrupto)'];
        }

        // Convertir y guardar a WebP con calidad 80
        $destination = $carpeta . '/' . $nombreBase . '.webp';
        $result = imagewebp($image, $destination, 80);
        imagedestroy($image); // Liberar memoria

        if ($result) {
            $consolaDir = basename(dirname($carpeta));
            $juegoDir   = basename($carpeta);
            return ['success' => true, 'filename' => 'public/uploads/' . $consolaDir . '/' . $juegoDir . '/' . $nombreBase . '.webp'];
        } else {
            return ['success' => false, 'error' => 'Error al convertir la imagen a WebP'];
        }
    }

    /**
     * Ruta absoluta de la carpeta del juego (public/uploads/{consola}/{juego}/).
     * Devuelve null si el juego usa la estructura antigua (portada en la raíz de uploads).
     */
    private function carpetaJuegoAbsoluta($juego): ?string {
        $rel = $juego['imagen'] ?? null;
        if (empty($rel) && !empty($juego['capturas'])) {
            $cap = Juego::parseCapturas($juego['capturas']);
            $rel = $cap[0] ?? null;
        }
        if (empty($rel)) {
            return null;
        }
        $partes = explode('/', trim($rel, '/'));
        if (count($partes) < 4 || ($partes[0] ?? '') !== 'public' || ($partes[1] ?? '') !== 'uploads') {
            return null; // estructura antigua en la raíz de uploads
        }
        return __DIR__ . '/../' . implode('/', array_slice($partes, 0, 3));
    }

    /** Devuelve una ruta absoluta libre para la carpeta del juego (agrega -2, -3... si ya existe). */
    private function resolverCarpetaJuego(string $dirBase, string $slug): string {
        $candidata = $dirBase . '/' . $slug;
        $n = 2;
        while (is_dir($candidata)) {
            $candidata = $dirBase . '/' . $slug . '-' . $n;
            $n++;
        }
        return $candidata;
    }

    /**
     * Resuelve (una sola vez por operación) y crea la carpeta del juego:
     * public/uploads/{consola-slug}/{juego-slug}/. Todas las imágenes del
     * juego (portada + capturas) se guardan dentro de esta misma carpeta.
     */
    private function carpetaDestinoJuego(?string $consolaNombre, string $titulo): string {
        $consolaSlug = $this->slugify((string)$consolaNombre);
        $juegoSlug   = $this->slugify($titulo);
        $dirBase     = __DIR__ . '/../public/uploads/' . $consolaSlug;
        if (!is_dir($dirBase)) {
            mkdir($dirBase, 0777, true);
        }
        $carpeta = $this->resolverCarpetaJuego($dirBase, $juegoSlug);
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        return $carpeta;
    }

    /** Mueve el contenido de $origen a $destino (creando $destino si hace falta). */
    private function moverContenido(string $origen, string $destino): void {
        if (!is_dir($origen)) {
            return;
        }
        if (!is_dir($destino)) {
            mkdir($destino, 0777, true);
        }
        foreach (scandir($origen) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            @rename($origen . '/' . $item, $destino . '/' . $item);
        }
    }

    /** Borra recursivamente una carpeta y todo su contenido. */
    private function rmdirRecursive(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /** Elimina los archivos de capturas anteriores referenciados en el JSON. */
    private function eliminarCapturasViejas($juego): void {
        foreach (Juego::parseCapturas($juego['capturas'] ?? null) as $ruta) {
            if ($ruta && file_exists($ruta)) {
                @unlink($ruta);
            }
        }
    }

    /** Nombre de la consola por ID (para construir el slug de carpeta). */
    private function obtenerConsolaNombre(int $consolaId): ?string {
        $consola = (new Consola())->find($consolaId);
        return $consola ? (string)$consola['nombre'] : null;
    }

    /** Slug seguro para carpetas: minúsculas, sin acentos, espacios → guiones. */
    private function slugify(string $s): string {
        $s = strtolower(trim($s));
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($conv !== false) {
            $s = $conv;
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        return $s === '' ? 'juego' : $s;
    }

    public function delete($id) {
        // Mutador vía GET — exigir token CSRF
        if (!CsrfService::verify()) {
            CsrfService::deny();
        }

        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);
        if ($juego) {
            // Estructura de carpetas: borrar la carpeta completa del juego
            $carpeta = $this->carpetaJuegoAbsoluta($juego);
            if ($carpeta && is_dir($carpeta)) {
                $this->rmdirRecursive($carpeta);
            } elseif ($juego['imagen'] && file_exists($juego['imagen'])) {
                // Estructura antigua: solo el archivo de la portada en la raíz de uploads
                @unlink($juego['imagen']);
            }
            $this->eliminarCapturasViejas($juego);
        }
        $juegoModel->delete($id);
        header('Location: index.php?controller=admin&action=dashboard');
        exit;
    }
}