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

        if (isset($_GET['busqueda']) && $_GET['busqueda'] !== '') {
            $filters['busqueda'] = $_GET['busqueda'];
        }

        if (isset($_GET['consola']) && $_GET['consola'] !== '') {
            $filters['consola'] = $_GET['consola'];
        }
        if (isset($_GET['categoria']) && $_GET['categoria'] !== '') {
            $filters['categoria'] = $_GET['categoria'];
        }
        if (isset($_GET['region']) && $_GET['region'] !== '') {
            $filters['region'] = $_GET['region'];
        }
        $filters['orden'] = (isset($_GET['orden']) && $_GET['orden'] !== '')
            ? $_GET['orden']
            : 'random';

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
        
        // Obtener datos para filtros
        $consolas = $consolaModel->all();
        $categorias = $categoriaModel->all();

        // PASAR TODAS LAS VARIABLES A LA VISTA
        require_once 'views/layout/header.php';
        require_once 'views/home/index.php';
        require_once 'views/layout/footer.php';
    }

    /**
     * Mapeo de nombre de consola → core de EmulatorJS.
     * Fuente: https://emulatorjs.org/docs/systems/
     * Core PSP confirmado como 'psp' en el demo oficial de EmulatorJS.
     */
    private function getEmulatorCore(string $consolaNombre): ?string {
        $nombre = strtolower(trim($consolaNombre));

        $mapa = [
            // Nintendo
            'nes'                           => 'nes',
            'famicom'                       => 'nes',
            'nintendo entertainment system' => 'nes',
            'snes'                          => 'snes',
            'super nintendo'                => 'snes',
            'super famicom'                 => 'snes',
            'game boy'                      => 'gb',
            'gameboy'                       => 'gb',
            'gb'                            => 'gb',
            'game boy color'                => 'gb',
            'gbc'                           => 'gb',
            'game boy advance'              => 'gba',
            'gba'                           => 'gba',
            'nintendo 64'                   => 'n64',
            'n64'                           => 'n64',
            'nintendo ds'                   => 'nds',
            'nds'                           => 'nds',
            'virtual boy'                   => 'vb',
            // Sega
            'sega master system'            => 'segaMS',
            'master system'                 => 'segaMS',
            'sega mega drive'               => 'segaMD',
            'mega drive'                    => 'segaMD',
            'genesis'                       => 'segaMD',
            'sega genesis'                  => 'segaMD',
            'sega game gear'                => 'segaGG',
            'game gear'                     => 'segaGG',
            'sega cd'                       => 'segaCD',
            'sega-cd'                       => 'segaCD',
            'mega-cd'                       => 'segaCD',
            'sega 32x'                      => 'sega32x',
            '32x'                           => 'sega32x',
            'sega saturn'                   => 'saturn',
            'saturn'                        => 'saturn',
            // Sony — core 'psp' confirmado en demo oficial EmulatorJS
            'playstation'                   => 'psx',
            'psx'                           => 'psx',
            'ps1'                           => 'psx',
            'playstation portable'          => 'ppsspp',
            'psp'                           => 'ppsspp',
            // Atari
            'atari 2600'                    => 'atari2600',
            'atari2600'                     => 'atari2600',
            'atari 7800'                    => 'atari7800',
            'atari 5200'                    => 'atari5200',
            'atari jaguar'                  => 'jaguar',
            'atari lynx'                    => 'lynx',
            // Arcade
            'arcade'                        => 'arcade',
            'mame'                          => 'mame2003',
            // Other
            '3do'                           => '3do',
            'colecovision'                  => 'coleco',
        ];

        if (isset($mapa[$nombre])) return $mapa[$nombre];

        foreach ($mapa as $clave => $core) {
            if (str_contains($nombre, $clave) || str_contains($clave, $nombre)) {
                return $core;
            }
        }

        return null;
    }

    /**
     * Cores que requieren BIOS alojado en el servidor.
     * Formato: 'core' => 'ruta/relativa/al/bios/desde/raiz'
     *
     * ─── INSTRUCCIONES BIOS ────────────────────────────────────────────────
     * PS1:  Sube tu BIOS a public/bios/ps1/scph1001.bin  (NTSC-U)
     *                               public/bios/ps1/scph5502.bin  (PAL)
     *       MD5 NTSC-U: 924e392ed05558ffdb115408c263dccf
     *       MD5 PAL:    32736f17079d0b2b7024407c39bd3050
     * ───────────────────────────────────────────────────────────────────────
     */
    private function getBiosUrl(string $core, string $region): ?string {
        $biosMap = [
            'psx' => [
                'PAL'    => 'public/bios/ps1/scph5502.bin',
                'NTSC'   => 'public/bios/ps1/scph1001.bin',
                'NTSC-U' => 'public/bios/ps1/scph1001.bin',
                'NTSC-J' => 'public/bios/ps1/scph1001.bin',
                'default'=> 'public/bios/ps1/scph1001.bin',
            ],
        ];

        if (!isset($biosMap[$core])) return null;

        $regionKey = isset($biosMap[$core][$region]) ? $region : 'default';
        $biosPath  = $biosMap[$core][$regionKey];

        // Solo devolver la URL si el archivo existe en disco
        return file_exists($biosPath) ? $biosPath : null;
    }

    /**
     * Cores que necesitan EJS_threads = true (requieren COOP/COEP headers).
     */
    private function requiresThreads(string $core): bool {
        return in_array($core, ['psp', 'dosbox_pure']);
    }

    /**
     * Cores con ISOs grandes: van directo en streaming sin precarga en memoria.
     */
    private function usarStreaming(string $core): bool {
        return in_array($core, ['psp', 'psx', 'saturn', 'segaCD', 'sega32x', '3do']);
    }

    /**
     * Reproducir un juego en línea usando EmulatorJS.
     */
    public function play() {
        $fileId = $_GET['file_id'] ?? null;

        if (!$fileId) {
            die("Error: No se especificó el juego a reproducir.");
        }

        $juegoModel = new Juego();
        $juego      = $juegoModel->findByFileId($fileId);

        if (!$juego) {
            die("Error: El juego solicitado no existe en la base de datos.");
        }

        // Registrar jugada online (solo cuando hay core disponible; se incrementa aquí
        // antes de resolver el core para contabilizar también intentos de consolas no soportadas).
        $juegoModel->incrementPlays($juego['id']);

        // Obtener nombre de consola si no viene del JOIN
        if (empty($juego['consola_nombre'])) {
            $consolaModel             = new Consola();
            $consola                  = $consolaModel->find($juego['consola_id']);
            $juego['consola_nombre']  = $consola['nombre'] ?? '';
        }

        $core    = $this->getEmulatorCore($juego['consola_nombre']);
        $romUrl  = "rom_proxy.php?file_id=" . urlencode($fileId);
        $biosUrl = null;
        $error   = null;

        if (!$core) {
            $error = "La consola «{$juego['consola_nombre']}» aún no está soportada por el emulador en línea.";
        } else {
            // Buscar BIOS si este core lo requiere
            $biosUrl = $this->getBiosUrl($core, $juego['region'] ?? '');

            // Si el core requiere BIOS y no está disponible, avisar
            if ($core === 'psx' && !$biosUrl) {
                $error = "El emulador de PlayStation requiere un archivo BIOS en el servidor. " .
                         "Sube el BIOS a <code>public/bios/ps1/scph1001.bin</code> para habilitar la emulación.";
            }
        }

        // Pasar flags a la vista
        $needsThreads  = $core ? $this->requiresThreads($core) : false;
        $modoStreaming  = $core ? $this->usarStreaming($core)   : false;

        // Verificar accesibilidad del archivo en Google Drive (solo si hay core)
        // HEAD ligero al proxy para detectar errores antes de cargar el emulador.
        $proxyError = null;
        if (!$error && $core) {
            $proxyError = $this->checkProxyAccess($fileId);
        }

        require_once 'views/layout/header.php';
        require_once 'views/home/play.php';
        require_once 'views/layout/footer.php';
    }

    /**
     * Hace un HEAD al proxy para detectar si el archivo de Google Drive
     * es accesible antes de intentar cargar el emulador.
     * Devuelve null si todo va bien, o un array ['type'=>..., 'message'=>...].
     */
    private function checkProxyAccess(string $fileId): ?array {
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
        $proxyUrl = $scheme . '://' . $host . $dir . '/rom_proxy.php?file_id=' . urlencode($fileId);

        $ch = curl_init($proxyUrl);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ROMs-Vault/Preflight',
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Si cURL no pudo conectar al proxy local, ignorar el error
        if ($httpCode === 0 || !empty($curlErr)) {
            return null;
        }
        if ($httpCode === 200 || $httpCode === 206) {
            return null; // Todo OK
        }

        $typeMap = [
            404 => ['type' => 'not_found', 'message' => 'El archivo de esta ROM ya no existe en Google Drive. Puede haber sido eliminado o el enlace está desactualizado.'],
            403 => ['type' => 'private',   'message' => 'Google Drive está bloqueando el acceso a este archivo. Puede que sea privado o que su cuota de descargas se haya agotado.'],
            429 => ['type' => 'quota',     'message' => 'Google Drive ha limitado temporalmente el acceso por exceso de descargas. Inténtalo de nuevo en unos minutos.'],
            502 => ['type' => 'network',   'message' => 'No se pudo conectar con Google Drive. El servicio puede estar caído o el archivo fue movido.'],
        ];

        return $typeMap[$httpCode] ?? [
            'type'    => 'network',
            'message' => "Error inesperado al acceder al archivo (código $httpCode). Inténtalo de nuevo más tarde.",
        ];
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