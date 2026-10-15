<?php
require_once __DIR__ . '/../models/Juego.php';
require_once __DIR__ . '/../models/Consola.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';
require_once __DIR__ . '/../config/CsrfService.php';
require_once __DIR__ . '/../config/TfaService.php';
require_once __DIR__ . '/../config/LoggerService.php';
require_once __DIR__ . '/../config/RateLimiter.php';
require_once __DIR__ . '/../config/Slug.php';
require_once __DIR__ . '/../config/StorageService.php';
require_once __DIR__ . '/../config/TraduccionService.php';
require_once __DIR__ . '/../config/IdiomasNormalizer.php';

class AdminController {
    // Máximo de capturas por juego (el carrusel ya replica la portada si no hay capturas)
    private const MAX_CAPTURAS = 7;

    /** Capa de almacenamiento (R2 o disco local según credenciales). */
    private StorageService $storage;

    public function __construct() {
        AuthMiddleware::requireAdmin();
        $this->storage = StorageService::make();
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
        require_once __DIR__ . '/../models/Consola.php';
        require_once __DIR__ . '/../models/Categoria.php';
        $consolas   = (new Consola())->all();
        $categorias = (new Categoria())->all();

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/dashboard.php';
        require_once __DIR__ . '/../views/layout/footer.php';
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

            // Normaliza la lista de idiomas a nombres canónicos antes de guardar
            // (evita re-ensuciar el campo y que la traducción reciba erratas).
            $data['idiomas'] = IdiomasNormalizer::normalizar($data['idiomas'] ?? null);

            $consolaNombre = $this->obtenerConsolaNombre((int)$data['consola_id']);

            // ── Prefijo del juego: {consola}/{juego}/ ──
            // Todas las imágenes (portada + capturas) van bajo este prefijo.
            $prefijoJuego = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);

            // ── Portada (un solo archivo) ──
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen'], $prefijoJuego, 'portada');
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
                    $capResult = $this->uploadImage($file, $prefijoJuego, 'captura-' . (count($rutas) + 1));
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
                // Al crear, el slug se genera siempre desde el título (el
                // formulario de alta no incluye el campo slug). En edición sí
                // puede fijarse a mano: ver resolverSlugJuego().
                $data['slug'] = Slug::unique($data['titulo'], fn(string $s) => $juegoModel->slugExists($s), 'juego');
                if ($juegoModel->create($data)) {
                    $this->autoTraducirJuego(
                        (int) $juegoModel->lastInsertId(),
                        (string) ($data['descripcion'] ?? ''),
                        (string) ($data['idiomas'] ?? '')
                    );
                    header('Location: /admin/dashboard');
                    exit;
                } else {
                    $error = t('msg.juego_guardar_error');
                }
            }
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/add.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    public function edit($id = null) {
        if (!$id) {
            die(t('msg.id_juego_no_especificado'));
        }
        
        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);
        
        if (!$juego) {
            die(t('msg.juego_no_encontrado_id', ['id' => $id]));
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

            // Normaliza la lista de idiomas a nombres canónicos antes de guardar.
            $data['idiomas'] = IdiomasNormalizer::normalizar($data['idiomas'] ?? null);

            $consolaAnterior = (int)$juego['consola_id'];
            $tituloAnterior  = $juego['titulo'];
            $consolaNuevaId  = (int)$data['consola_id'];
            $cambioDestino   = $consolaNuevaId !== $consolaAnterior || $data['titulo'] !== $tituloAnterior;

            $consolaNombre = $this->obtenerConsolaNombre($consolaNuevaId);
            $prefijoViejo   = $this->prefijoJuego($juego);
            $prefijoDestino = $prefijoViejo;

            // ── Si cambió consola/título: mover los objetos a su nueva ubicación ──
            if ($cambioDestino) {
                $prefijoDestino = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);
                if ($prefijoViejo !== null && $prefijoViejo !== $prefijoDestino) {
                    $this->storage->copiarPrefijo($prefijoViejo, $prefijoDestino);
                    $this->storage->borrarPrefijo($prefijoViejo);
                    // Actualizar las referencias viejas → nuevas para operar sobre la nueva ubicación
                    $juego['imagen']   = $this->reubicarReferencia($juego['imagen'] ?? null, $prefijoDestino);
                    $nuevasRutas = [];
                    foreach (Juego::parseCapturas($juego['capturas'] ?? null) as $cap) {
                        $nuevasRutas[] = $this->reubicarReferencia($cap, $prefijoDestino);
                    }
                    $juego['capturas'] = json_encode($nuevasRutas);
                    // Traducir también las referencias marcadas para eliminar (el formulario envió las viejas)
                    if (!empty($_POST['eliminar_capturas']) && is_array($_POST['eliminar_capturas'])) {
                        $_POST['eliminar_capturas'] = array_map(
                            fn($r) => $this->reubicarReferencia((string) $r, $prefijoDestino),
                            $_POST['eliminar_capturas']
                        );
                    }
                }
            } elseif ($prefijoDestino === null) {
                // Estructura antigua (portada suelta): crear prefijo nuevo
                $prefijoDestino = $this->carpetaDestinoJuego($consolaNombre, $data['titulo']);
            }

            // ── Portada (un solo archivo) ──
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenResult = $this->uploadImage($_FILES['imagen'], $prefijoDestino, 'portada');
                if ($imagenResult['success']) {
                    // Eliminar la portada anterior si es un archivo distinto
                    $claveVieja = $this->storage->keyDeReferencia($juego['imagen'] ?? null);
                    $claveNueva = $this->storage->keyDeReferencia($imagenResult['filename']);
                    if ($claveVieja !== null && $claveVieja !== $claveNueva) {
                        $this->storage->borrar($claveVieja);
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
                    $clave = $this->storage->keyDeReferencia($ruta);
                    if ($clave !== null) {
                        $this->storage->borrar($clave);
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
                    $capResult = $this->uploadImage($file, $prefijoDestino, 'captura-' . (count($restantes) + count($nuevas) + 1));
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
                $data['slug'] = $this->resolverSlugJuego(
                    $_POST['slug'] ?? '',
                    $data['titulo'],
                    $juegoModel,
                    $juego['slug'] ?? null,
                    (int) $juego['id']
                );
                if ($juegoModel->update($id, $data)) {
                    $this->autoTraducirJuego(
                        (int) $id,
                        (string) ($data['descripcion'] ?? ''),
                        (string) ($data['idiomas'] ?? '')
                    );
                    header('Location: /admin/dashboard');
                    exit;
                } else {
                    $error = t('msg.juego_actualizar_error');
                }
            }
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/edit.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Auto-traduce la descripción y los idiomas de un juego a los idiomas
     * destino (best-effort, no bloquea el guardado: el registro ya está
     * persistido). Solo se traducen la descripción y la lista de idiomas: el
     * título es nombre propio y no se traduce (decisión de diseño). Sin
     * proveedor configurado no hace nada.
     */
    private function autoTraducirJuego(int $id, string $descripcion, string $idiomas = ''): void {
        if ($id <= 0 || (trim($descripcion) === '' && trim($idiomas) === '')) {
            return;
        }
        $campos = [
            'descripcion' => $descripcion,
            'idiomas'     => $idiomas,
        ];
        // Con TRANSLATE_MODE=async el trabajo se encola y el worker CLI lo
        // traduce en segundo plano (el admin no espera a las 11 llamadas).
        if (TraduccionService::modo() === 'async') {
            TraduccionService::encolar('juego', $id, $campos);
            return;
        }
        TraduccionService::traducirEntidad('juego', $id, $campos);
    }

    public function toggleActive($id) {
        // Mutador vía GET — exigir token CSRF
        if (!CsrfService::verify()) {
            CsrfService::deny();
        }

        if (!$id) {
            header('Location: /admin/dashboard');
            exit;
        }

        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);

        if ($juego) {
            $nuevoEstado = $juego['activo'] ? 0 : 1;
            $juegoModel->update($id, ['activo' => $nuevoEstado]);
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        header('Location: /admin/dashboard?page=' . $page);
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
            echo json_encode(['ok' => false, 'error' => t('msg.id_no_especificado')]);
            exit;
        }

        $juegoModel = new Juego();
        $juego      = $juegoModel->find($id);

        if (!$juego) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => t('msg.juego_no_encontrado')]);
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
     * Gestión de la verificación en dos pasos (2FA TOTP) del admin actual.
     *
     * GET: muestra el estado actual; si hay un secreto pendiente de confirmar
     * (generado con 'activar'), lo muestra para escanearlo en la app.
     *
     * POST (acciones, siempre protegidas por el CSRF global de index.php):
     *   - activar     → genera/rota un secreto TOTP y lo deja pendiente
     *   - confirmar   → verifica un código contra el secreto y activa el 2FA
     *   - desactivar  → exige la contraseña actual y revoca el 2FA
     */
    public function tfa() {
        $admin  = AuthMiddleware::requireAdmin();
        $userId = (int) $admin['user_id'];
        $usuarioModel = new Usuario();
        $fila   = $usuarioModel->find($userId);
        $ip     = RateLimiter::clientIp();

        $msg     = null;
        $msgTipo = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'activar') {
                $secret = TfaService::generarSecret();
                $usuarioModel->registrarSecretTfa($userId, $secret);
                $msg     = t('msg.tfa_activar_instruccion');
                $msgTipo = 'info';
            } elseif ($accion === 'confirmar') {
                $secret = $usuarioModel->obtenerSecretTfa($userId);
                $code   = trim((string) ($_POST['code'] ?? ''));

                if ($secret === null) {
                    $msg     = t('msg.tfa_generar_primero');
                    $msgTipo = 'danger';
                } elseif (!TfaService::verificar($secret, $code)) {
                    LoggerService::tfaFailed((string) ($fila['username'] ?? ''), $ip);
                    $msg     = t('msg.tfa_codigo_incorrecto_activacion');
                    $msgTipo = 'danger';
                } else {
                    $usuarioModel->habilitarTfa($userId);
                    LoggerService::write('tfa_enabled', [
                        'username' => $fila['username'] ?? '',
                        'ip'       => $ip,
                    ]);
                    // Invalidar la sesión actual: debe volver a entrar con 2FA
                    JWTService::clearTokenCookie();
                    header('Location: /auth/login?tfa=activado');
                    exit;
                }
            } elseif ($accion === 'desactivar') {
                $password = (string) ($_POST['password'] ?? '');

                if (!$usuarioModel->verifyPassword($password, $fila['password_hash'] ?? '')) {
                    $msg     = t('msg.tfa_contrasena_incorrecta');
                    $msgTipo = 'danger';
                } else {
                    $usuarioModel->deshabilitarTfa($userId);
                    LoggerService::write('tfa_disabled', [
                        'username' => $fila['username'] ?? '',
                        'ip'       => $ip,
                    ]);
                    JWTService::clearTokenCookie();
                    header('Location: /auth/login?tfa=desactivado');
                    exit;
                }
            }
        }

        // Estado para la vista
        $tfaActivo = !empty($fila['tfa_enabled']);
        $secretPendiente = null;
        $provisionUri    = null;
        if (!$tfaActivo) {
            $secretPendiente = $usuarioModel->obtenerSecretTfa($userId);
            if ($secretPendiente !== null) {
                $provisionUri = TfaService::provisionUri((string) $fila['username'], $secretPendiente);
            }
        }

        require_once __DIR__ . '/../views/layout/header.php';
        require_once __DIR__ . '/../views/admin/tfa.php';
        require_once __DIR__ . '/../views/layout/footer.php';
    }

    /**
     * Sube y convierte una imagen a WebP bajo el prefijo del juego.
     *
     * El prefijo destino ya viene resuelto (carpetaDestinoJuego() o el
     * prefijo existente del juego); NO se vuelve a resolver aquí, para
     * que portada y capturas compartan el mismo prefijo.
     *
     * @param array  $file       Archivo del formulario ($_FILES['...'])
     * @param string $prefijo    Prefijo relativo del juego: "{consola}/{juego}"
     * @param string $nombreBase Nombre base del archivo: 'portada' o 'captura-N'
     * @return array{success: bool, filename?: string, error?: string}
     */
    private function uploadImage($file, string $prefijo, string $nombreBase) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'error' => t('msg.archivo_tipo_no_permitido')];
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => t('msg.imagen_demasiado_grande')];
        }

        // Verificar si la librería GD está activa en el servidor
        if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
            return ['success' => false, 'error' => t('msg.gd_no_activada')];
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
            return ['success' => false, 'error' => t('msg.imagen_procesar_error')];
        }

        // Convertir a WebP (calidad 80) en memoria y subir al almacenamiento
        ob_start();
        $ok = imagewebp($image, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($image); // Liberar memoria

        if (!$ok || !is_string($bytes) || $bytes === '') {
            return ['success' => false, 'error' => t('msg.imagen_webp_error')];
        }

        $key = rtrim($prefijo, '/') . '/' . $nombreBase . '.webp';
        $referencia = $this->storage->guardar($key, $bytes, 'image/webp');
        return ['success' => true, 'filename' => $referencia];
    }

    /**
     * Prefijo relativo del juego ("{consola}/{juego}") a partir de sus referencias.
     * Devuelve null si el juego usa la estructura antigua (archivo suelto en uploads).
     */
    private function prefijoJuego($juego): ?string {
        $ref = $juego['imagen'] ?? null;
        if (empty($ref) && !empty($juego['capturas'])) {
            $cap = Juego::parseCapturas($juego['capturas']);
            $ref = $cap[0] ?? null;
        }
        $key = $this->storage->keyDeReferencia($ref);
        if ($key === null) {
            return null;
        }
        $partes = explode('/', $key);
        if (count($partes) < 3) {
            return null; // estructura antigua: archivo suelto en la raíz
        }
        return implode('/', array_slice($partes, 0, 2));
    }

    /** Devuelve un prefijo libre para el juego (agrega -2, -3... si ya está ocupado). */
    private function resolverPrefijoJuego(string $consolaSlug, string $juegoSlug): string {
        $candidata = $consolaSlug . '/' . $juegoSlug;
        $n = 2;
        while ($this->storage->prefijoOcupado($candidata)) {
            $candidata = $consolaSlug . '/' . $juegoSlug . '-' . $n;
            $n++;
        }
        return $candidata;
    }

    /**
     * Resuelve el prefijo del juego: {consola-slug}/{juego-slug}. Todas las
     * imágenes del juego (portada + capturas) se guardan bajo este prefijo.
     */
    private function carpetaDestinoJuego(?string $consolaNombre, string $titulo): string {
        $consolaSlug = $this->slugify((string)$consolaNombre);
        $juegoSlug   = $this->slugify($titulo);
        return $this->resolverPrefijoJuego($consolaSlug, $juegoSlug);
    }

    /** Reescribe una referencia moviéndola al prefijo destino (mismo nombre de archivo). */
    private function reubicarReferencia(?string $ref, string $prefijoDestino): string {
        $key = $this->storage->keyDeReferencia($ref);
        if ($key === null) {
            return (string) $ref; // no gestionable: se deja igual
        }
        $nuevaKey = rtrim($prefijoDestino, '/') . '/' . basename($key);
        return $this->storage->referencia($nuevaKey);
    }

    /** Elimina los archivos de capturas anteriores referenciados en el JSON. */
    private function eliminarCapturasViejas($juego): void {
        foreach (Juego::parseCapturas($juego['capturas'] ?? null) as $ruta) {
            $key = $this->storage->keyDeReferencia($ruta);
            if ($key !== null) {
                $this->storage->borrar($key);
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
        return Slug::make($s, 'juego');
    }

    /**
     * Resuelve el slug de un juego (URL limpia /juego/{slug}).
     *  - Si el admin escribe uno a mano, se respeta (slugificado + único).
     *  - Si no, en edición se conserva el slug actual (estable: editar el
     *    título NO cambia la URL, decisión D2 del ítem 7.2).
     *  - Si no hay slug previo, se genera desde el título.
     * $excludeId evita que el propio registro colisione consigo mismo.
     */
    private function resolverSlugJuego(
        string $manual,
        string $titulo,
        Juego $model,
        ?string $slugActual = null,
        ?int $excludeId = null
    ): string {
        $manual = trim($manual);
        if ($manual !== '') {
            return Slug::unique($manual, fn(string $s) => $model->slugExists($s, $excludeId), 'juego');
        }
        if ($slugActual !== null && $slugActual !== '') {
            return $slugActual;
        }
        return Slug::unique($titulo, fn(string $s) => $model->slugExists($s, $excludeId), 'juego');
    }

    public function delete($id) {
        // Mutador vía GET — exigir token CSRF
        if (!CsrfService::verify()) {
            CsrfService::deny();
        }

        $juegoModel = new Juego();
        $juego = $juegoModel->find($id);
        if ($juego) {
            // Estructura nueva: borrar todo el prefijo del juego
            $prefijo = $this->prefijoJuego($juego);
            if ($prefijo !== null) {
                $this->storage->borrarPrefijo($prefijo);
            } else {
                // Estructura antigua: solo el archivo de la portada suelto
                $clavePortada = $this->storage->keyDeReferencia($juego['imagen'] ?? null);
                if ($clavePortada !== null) {
                    $this->storage->borrar($clavePortada);
                }
            }
            $this->eliminarCapturasViejas($juego);
        }
        $juegoModel->delete($id);
        header('Location: /admin/dashboard');
        exit;
    }
}