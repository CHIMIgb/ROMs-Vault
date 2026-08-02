<?php
require_once 'Model.php';

class Juego extends Model {
    protected $table = 'juegos';

    public function find($id) {
        if (!$id) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result;
    }

    // Buscar juego por google_drive_file_id
    public function findByFileId($fileId) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE google_drive_file_id = ?");
        $stmt->execute([$fileId]);
        return $stmt->fetch();
    }
    
    // Obtener un juego por ID con detalles de relaciones
    public function findWithDetails($id) {
        $stmt = $this->pdo->prepare("SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.id = ? AND j.activo = true");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Incrementar contador de descargas
    public function incrementDownloads($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET downloads_count = downloads_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Incrementar contador de jugadas online
    public function incrementPlays($id) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET plays_count = plays_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getWithRelations($filters = []) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true";

        $params = [];

        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
            $params['consola'] = $filters['consola'];
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
            $params['categoria'] = $filters['categoria'];
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
            $params['region'] = $filters['region'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDownloadLink($fileId) {
        return "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t";
    }

    // Todos los juegos (activos e inactivos) paginados para el dashboard
    public function getAllPaginated($offset = 0, $limit = 20, $busqueda = null) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id";

        if ($busqueda) {
            $sql .= " WHERE j.titulo ILIKE :busqueda";
        }

        $sql .= " ORDER BY j.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        if ($busqueda) {
            $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar todos los juegos (activos e inactivos) para la paginación del dashboard
    public function countAll($busqueda = null) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";

        if ($busqueda) {
            $sql .= " WHERE titulo ILIKE :busqueda";
        }

        $stmt = $this->pdo->prepare($sql);

        if ($busqueda) {
            $stmt->bindValue(':busqueda', '%' . $busqueda . '%', PDO::PARAM_STR);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }

    /**
     * Whitelist de ordenamientos permitidos para el catálogo público.
     * Previene inyección SQL al construir el ORDER BY dinámicamente.
     */
    private function resolveOrder(string $orden): string {
        $map = [
            'titulo'    => 'j.titulo ASC',
            'recientes' => 'j.created_at DESC',
            'descargas' => 'j.downloads_count DESC',
            'jugados'   => 'j.plays_count DESC',
            'año_asc'   => 'j.fecha_lanzamiento ASC NULLS LAST',
            'año_desc'  => 'j.fecha_lanzamiento DESC NULLS LAST',
            'random'    => 'RANDOM()',
        ];
        return $map[$orden] ?? 'RANDOM()';
    }

    // Método con paginación, búsqueda y ordenamiento (catálogo público)
    public function getWithRelationsPaginated($filters = [], $offset = 0, $limit = 20) {
        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre 
                FROM juegos j
                LEFT JOIN consolas c ON j.consola_id = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true";

        if (!empty($filters['busqueda'])) {
            $sql .= " AND j.titulo ILIKE :busqueda";
        }
        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
        }

        // Ordenamiento seguro mediante whitelist
        $orden = $this->resolveOrder($filters['orden'] ?? '');
        $sql .= " ORDER BY {$orden} LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        if (!empty($filters['consola'])) {
            $stmt->bindValue(':consola', $filters['consola'], PDO::PARAM_INT);
        }
        if (!empty($filters['categoria'])) {
            $stmt->bindValue(':categoria', $filters['categoria'], PDO::PARAM_INT);
        }
        if (!empty($filters['region'])) {
            $stmt->bindValue(':region', $filters['region'], PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar juegos con filtros y búsqueda (catálogo público)
    public function countWithFilters($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM juegos j WHERE j.activo = true";

        if (!empty($filters['busqueda'])) {
            $sql .= " AND j.titulo ILIKE :busqueda";
        }
        if (!empty($filters['consola'])) {
            $sql .= " AND j.consola_id = :consola";
        }
        if (!empty($filters['categoria'])) {
            $sql .= " AND j.categoria_id = :categoria";
        }
        if (!empty($filters['region'])) {
            $sql .= " AND j.region = :region";
        }

        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($filters['busqueda'])) {
            $stmt->bindValue(':busqueda', '%' . $filters['busqueda'] . '%', PDO::PARAM_STR);
        }
        if (!empty($filters['consola'])) {
            $stmt->bindValue(':consola', $filters['consola'], PDO::PARAM_INT);
        }
        if (!empty($filters['categoria'])) {
            $stmt->bindValue(':categoria', $filters['categoria'], PDO::PARAM_INT);
        }
        if (!empty($filters['region'])) {
            $stmt->bindValue(':region', $filters['region'], PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }

    // ── ADMIN: filtros extendidos ─────────────────────────────────────────

    /**
     * Construye la cláusula WHERE compartida para las consultas del admin.
     * Devuelve ['sql' => string, 'params' => array]
     */
    private function buildAdminWhere(array $f): array {
        $where  = [];
        $params = [];

        if (!empty($f['busqueda'])) {
            $where[]              = "j.titulo ILIKE :busqueda";
            $params['busqueda']   = '%' . $f['busqueda'] . '%';
        }
        if (!empty($f['consola'])) {
            $where[]            = "j.consola_id = :consola";
            $params['consola']  = (int)$f['consola'];
        }
        if (!empty($f['categoria'])) {
            $where[]              = "j.categoria_id = :categoria";
            $params['categoria']  = (int)$f['categoria'];
        }
        if (!empty($f['region'])) {
            $where[]           = "j.region = :region";
            $params['region']  = $f['region'];
        }
        // activo: '' = todos, '1' = activos, '0' = inactivos
        if (isset($f['activo']) && $f['activo'] !== '') {
            $where[]           = "j.activo = :activo";
            $params['activo']  = (bool)(int)$f['activo'];
        }

        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        return ['sql' => $sql, 'params' => $params];
    }

    // Todos los juegos paginados para el dashboard con filtros extendidos
    public function getAllPaginatedFiltered(array $filters, int $offset, int $limit): array {
        $w = $this->buildAdminWhere($filters);

        $sql = "SELECT j.*, c.nombre as consola_nombre, cat.nombre as categoria_nombre
                FROM juegos j
                LEFT JOIN consolas c   ON j.consola_id   = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id"
             . $w['sql']
             . " ORDER BY j.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($w['params'] as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
            $stmt->bindValue(":$k", $v, $type);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar con filtros extendidos (admin)
    public function countAllFiltered(array $filters): int {
        $w   = $this->buildAdminWhere($filters);
        $sql = "SELECT COUNT(*) as total FROM juegos j" . $w['sql'];
        $stmt = $this->pdo->prepare($sql);
        foreach ($w['params'] as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
            $stmt->bindValue(":$k", $v, $type);
        }
        $stmt->execute();
        return (int)$stmt->fetch()['total'];
    }

    // ── ADMIN: estadísticas globales reales ───────────────────────────────

    public function getGlobalStats(): array {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                            AS total,
                COUNT(*) FILTER (WHERE activo)      AS activos,
                COUNT(*) FILTER (WHERE NOT activo)  AS inactivos,
                COALESCE(SUM(downloads_count), 0)   AS total_descargas,
                COALESCE(SUM(plays_count), 0)       AS total_jugadas
             FROM juegos"
        )->fetch();

        return [
            'total'            => (int)$row['total'],
            'activos'          => (int)$row['activos'],
            'inactivos'        => (int)$row['inactivos'],
            'total_descargas'  => (int)$row['total_descargas'],
            'total_jugadas'    => (int)$row['total_jugadas'],
        ];
    }

    // Top N juegos por descargas
    public function getTopByDownloads(int $n = 5): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.titulo, c.nombre as consola_nombre, j.downloads_count
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.downloads_count > 0
             ORDER BY j.downloads_count DESC
             LIMIT ?"
        );
        $stmt->execute([$n]);
        return $stmt->fetchAll();
    }

    // Top N juegos por jugadas online
    public function getTopByPlays(int $n = 5): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.titulo, c.nombre as consola_nombre, j.plays_count
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.plays_count > 0
             ORDER BY j.plays_count DESC
             LIMIT ?"
        );
        $stmt->execute([$n]);
        return $stmt->fetchAll();
    }

    /**
     * Familias de género: categorías que, aunque no compartan nombre exacto,
     * pertenecen al mismo "género padre". Se usan como respaldo para que la
     * sección de juegos del mismo género siempre tenga contenido.
     * Los términos están normalizados (minúsculas, sin acentos).
     */
    private const FAMILIAS_GENERO = [
        'rol'         => ['rol', 'rpg', 'jrpg'],
        'terror'      => ['terror', 'survival horror'],
        'carreras'    => ['carreras', 'kart'],
        'deportes'    => ['deportes', 'deportes extremos'],
        'disparos'    => ['disparos', 'shooter', 'shoot em up', 'run and gun'],
        'peleas'      => ['peleas', 'lucha libre', 'beat em up'],
        'plataformas' => ['plataformas', 'metroidvania'],
        'puzzle'      => ['puzzle', 'rompecabezas', 'mini-juegos', 'party games'],
        'aventura'    => ['aventura', 'visual novel', 'novela visual'],
        'arcade'      => ['arcade', 'pinball', 'shoot em up', 'run and gun'],
        'simulacion'  => ['simulacion', 'simulacion de vuelo'],
    ];

    /**
     * Palabras genéricas que no sirven para relacionar juegos por el nombre.
     * (Las palabras cortas —menos de 4 letras— se filtran por longitud.)
     */
    private const STOPWORDS_TITULO = [
        'the', 'and', 'for', 'with', 'versus', 'edition', 'collection', 'volume',
        'special', 'deluxe', 'ultimate', 'greatest', 'hits', 'world', 'para',
        'sobre', 'entre',
    ];

    /**
     * Juegos relacionados divididos en dos secciones curadas:
     *   1. misma consola   → relevancia 2 (sección "Más de X")
     *   2. mismo género    → relevancia 1 (sección "Géneros similares")
     *
     * La sección de género SIEMPRE se intenta llenar, en este orden y con
     * orden aleatorio para dar variedad:
     *   1. juegos de la misma categoría (otras consolas)
     *   2. categorías hermanas / géneros muy relacionados
     *   3. juegos relacionados por el nombre (palabras clave del título)
     *   4. último recurso: cualquier otro juego activo, para que la sección
     *      nunca aparezca vacía cuando existan más juegos en la colección.
     *
     * @return array Juegos con las columnas de `juegos`, `consola_nombre`,
     *               `categoria_nombre` y `relevancia` (2 = consola, 1 = género).
     */
    public function getRelated(int $juegoId, int $consolaId, int $categoriaId, int $limit = 8): array {
        $porSeccion = max(1, (int) floor($limit / 2));

        $mismaConsola = $this->relacionadosPorConsola($juegoId, $consolaId, $porSeccion);

        // Evitamos duplicados: la sección de género nunca repite juegos de la de consola
        $idsExcluidos = array_map('intval', array_column($mismaConsola, 'id'));
        $idsExcluidos[] = $juegoId;

        $mismoGenero = $this->relacionadosPorGenero($juegoId, $consolaId, $categoriaId, $porSeccion, $idsExcluidos);

        return array_merge($mismaConsola, $mismoGenero);
    }

    /**
     * Sección 1: juegos de la misma consola (relevancia 2).
     * Orden aleatorio con sesgo de popularidad (comportamiento original).
     */
    private function relacionadosPorConsola(int $juegoId, int $consolaId, int $limit): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.*, c.nombre AS consola_nombre, cat.nombre AS categoria_nombre,
                    2 AS relevancia
             FROM juegos j
             LEFT JOIN consolas   c   ON j.consola_id   = c.id
             LEFT JOIN categorias cat ON j.categoria_id = cat.id
             WHERE j.activo = true
               AND j.id != :juego_id
               AND j.consola_id = :consola_id
             ORDER BY (j.downloads_count * RANDOM()) DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':juego_id',   $juegoId,   PDO::PARAM_INT);
        $stmt->bindValue(':consola_id', $consolaId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',        $limit,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Sección 2: juegos del mismo género (relevancia 1) con 4 niveles de
     * respaldo para garantizar contenido.
     *
     * @param int   $juegoId    Juego actual (se excluye)
     * @param int   $consolaId  Consola del juego actual
     * @param int   $categoriaId Categoría del juego actual
     * @param int   $limit      Cuántos juegos de género se quieren
     * @param array $excluirIds Ids que no se deben repetir (incluye al actual)
     */
    private function relacionadosPorGenero(int $juegoId, int $consolaId, int $categoriaId, int $limit, array $excluirIds): array {
        $resultado = [];
        $excluir   = array_values(array_unique(array_merge([$juegoId], array_map('intval', $excluirIds))));
        $faltan    = $limit;

        // Nivel 1: mismo género exacto, preferentemente de otras consolas
        if ($faltan > 0) {
            $fila = $this->queryGenero(
                $juegoId,
                'AND j.categoria_id = :categoria_id AND j.consola_id != :consola_id',
                ['categoria_id' => $categoriaId, 'consola_id' => $consolaId],
                $faltan,
                $excluir
            );
            $resultado = array_merge($resultado, $fila);
            foreach ($fila as $j) { $excluir[] = (int) $j['id']; }
            $faltan -= count($fila);
        }

        // Nivel 2: géneros muy relacionados (categorías hermanas)
        if ($faltan > 0) {
            $categoriasRel = $this->categoriasRelacionadas($categoriaId);
            if ($categoriasRel) {
                $in     = [];
                $params = [];
                foreach ($categoriasRel as $i => $cid) {
                    $ph          = ':cat' . $i;
                    $in[]        = $ph;
                    $params[$ph] = (int) $cid;
                }
                $fila = $this->queryGenero(
                    $juegoId,
                    'AND j.categoria_id IN (' . implode(',', $in) . ')',
                    $params,
                    $faltan,
                    $excluir
                );
                $resultado = array_merge($resultado, $fila);
                foreach ($fila as $j) { $excluir[] = (int) $j['id']; }
                $faltan -= count($fila);
            }
        }

        // Nivel 3: juegos relacionados por el nombre (palabras clave del título)
        if ($faltan > 0) {
            $juego    = $this->findWithDetails($juegoId);
            $palabras = $juego ? $this->palabrasClaveTitulo($juego['titulo'] ?? '') : [];
            if ($palabras) {
                $ors    = [];
                $params = [];
                foreach ($palabras as $i => $w) {
                    $ph          = ':kw' . $i;
                    $ors[]       = "j.titulo ILIKE {$ph}";
                    $params[$ph] = '%' . $w . '%';
                }
                $fila = $this->queryGenero(
                    $juegoId,
                    'AND (' . implode(' OR ', $ors) . ')',
                    $params,
                    $faltan,
                    $excluir
                );
                $resultado = array_merge($resultado, $fila);
                foreach ($fila as $j) { $excluir[] = (int) $j['id']; }
                $faltan -= count($fila);
            }
        }

        // Nivel 4: último recurso — variedad general para no dejar la sección vacía
        if ($faltan > 0) {
            $fila = $this->queryGenero($juegoId, '', [], $faltan, $excluir);
            $resultado = array_merge($resultado, $fila);
        }

        return $resultado;
    }

    /**
     * Ejecuta una consulta de la sección de género (relevancia 1) excluyendo
     * ids ya usados, con orden aleatorio.
     *
     * @param int    $juegoId    Id del juego actual
     * @param string $whereExtra Fragmentos SQL adicionales (con sus placeholders)
     * @param array  $params     Valores para los placeholders de $whereExtra
     * @param int    $limit      Límite de resultados
     * @param array  $excluir    Ids a excluir (además del juego actual)
     */
    private function queryGenero(int $juegoId, string $whereExtra, array $params, int $limit, array $excluir): array {
        $bind   = [];
        $excluir = array_values(array_unique(array_map('intval', $excluir)));

        $bind[':juego_id'] = [$juegoId, PDO::PARAM_INT];
        $bind[':lim']      = [$limit,   PDO::PARAM_INT];
        foreach ($params as $k => $v) {
            $bind[$k] = [$v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR];
        }

        $notIn = '';
        if ($excluir) {
            $places = [];
            foreach ($excluir as $i => $id) {
                $ph           = ':ex' . $i;
                $places[]     = $ph;
                $bind[$ph]    = [$id, PDO::PARAM_INT];
            }
            $notIn = ' AND j.id NOT IN (' . implode(',', $places) . ')';
        }

        $sql = "SELECT j.*, c.nombre AS consola_nombre, cat.nombre AS categoria_nombre,
                    1 AS relevancia
                FROM juegos j
                LEFT JOIN consolas   c   ON j.consola_id   = c.id
                LEFT JOIN categorias cat ON j.categoria_id = cat.id
                WHERE j.activo = true
                  AND j.id != :juego_id
                  {$whereExtra}
                  {$notIn}
                ORDER BY RANDOM()
                LIMIT :lim";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $ph => $def) {
            $stmt->bindValue($ph, $def[0], $def[1]);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * IDs de categorías "hermanas": comparten una palabra significativa en el
     * nombre o pertenecen a la misma familia de género (FAMILIAS_GENERO).
     */
    private function categoriasRelacionadas(int $categoriaId): array {
        require_once 'Categoria.php';
        $categorias = (new Categoria())->allActivas();

        $actual = null;
        foreach ($categorias as $cat) {
            if ((int) $cat['id'] === $categoriaId) {
                $actual = $cat;
                break;
            }
        }
        if (!$actual) {
            return [];
        }

        $nombreActual     = $this->normalizar($actual['nombre']);
        $palabrasActuales = $this->palabrasSignificativas($nombreActual);
        $familiaActual    = $this->familiaDeGenero($nombreActual);

        $relacionadas = [];
        foreach ($categorias as $cat) {
            $id = (int) $cat['id'];
            if ($id === $categoriaId) {
                continue;
            }

            $nombre   = $this->normalizar($cat['nombre']);
            $palabras = $this->palabrasSignificativas($nombre);

            // 1) Comparte al menos una palabra significativa
            if (array_intersect($palabrasActuales, $palabras)) {
                $relacionadas[] = $id;
                continue;
            }

            // 2) Misma familia de género (p. ej. Rol → JRPG, Terror → Survival Horror)
            if ($familiaActual) {
                foreach (self::FAMILIAS_GENERO[$familiaActual] as $termino) {
                    if (strpos($nombre, $termino) !== false) {
                        $relacionadas[] = $id;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($relacionadas));
    }

    /**
     * Devuelve la clave de la familia de género a la que pertenece un nombre
     * de categoría normalizado, o null si no está en el mapa.
     */
    private function familiaDeGenero(string $nombreNormalizado): ?string {
        foreach (self::FAMILIAS_GENERO as $familia => $terminos) {
            foreach ($terminos as $termino) {
                if (strpos($nombreNormalizado, $termino) !== false) {
                    return $familia;
                }
            }
        }
        return null;
    }

    /**
     * Palabras significativas de un título para buscar relacionados por nombre.
     * Ignora stopwords y limita a 4 palabras para no sobre-ampliar la búsqueda.
     */
    private function palabrasClaveTitulo(string $titulo): array {
        $normalizado = $this->normalizar($titulo);
        $palabras    = preg_split('/\s+/', $normalizado, -1, PREG_SPLIT_NO_EMPTY);
        $claves      = [];

        foreach ($palabras as $p) {
            if (strlen($p) >= 4 && !in_array($p, self::STOPWORDS_TITULO, true)) {
                $claves[] = $p;
            }
        }

        return array_slice(array_values(array_unique($claves)), 0, 4);
    }

    /**
     * Palabras significativas (>= 3 letras) de un texto normalizado.
     */
    private function palabrasSignificativas(string $nombre): array {
        $palabras = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
        $resultado = [];
        foreach ($palabras as $p) {
            if (strlen($p) >= 3) {
                $resultado[] = $p;
            }
        }
        return $resultado;
    }

    /**
     * Normaliza un texto para comparaciones: minúsculas, sin acentos, sin
     * puntuación y con palabras separadas por un solo espacio.
     */
    private function normalizar(string $s): string {
        $s   = strtolower(trim($s));
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($conv !== false) {
            $s = $conv;
        }
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim((string) $s);
    }

    /**
     * Autocompletado: busca títulos que coincidan con el término.
     * Devuelve id, titulo, consola_nombre, imagen para sugerencias rápidas.
     */
    public function autocomplete(string $term, int $limit = 8): array {
        $stmt = $this->pdo->prepare(
            "SELECT j.id, j.titulo, j.google_drive_file_id, j.imagen,
                    c.nombre as consola_nombre
             FROM juegos j
             LEFT JOIN consolas c ON j.consola_id = c.id
             WHERE j.activo = true AND j.titulo ILIKE :term
             ORDER BY j.downloads_count DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':term', '%' . $term . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim',  $limit,            PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Estadísticas contextuales de la colección para la ficha de detalle.
     * Devuelve la posición del juego por descargas/jugadas y los totales
     * de la consola y del género, para reforzar el contexto de coleccionista.
     */
    public function getCatalogStats(int $juegoId, int $consolaId, int $categoriaId, int $downloadsCount, int $playsCount): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM juegos j1 WHERE j1.activo = true AND j1.downloads_count > :dl) + 1 AS rank_descargas,
                (SELECT COUNT(*) FROM juegos j2 WHERE j2.activo = true AND j2.plays_count    > :pl) + 1 AS rank_jugadas,
                (SELECT COUNT(*) FROM juegos j3 WHERE j3.activo = true AND j3.consola_id    = :consola)   AS total_consola,
                (SELECT COUNT(*) FROM juegos j4 WHERE j4.activo = true AND j4.categoria_id  = :categoria) AS total_genero"
        );
        $stmt->execute([
            ':dl'        => $downloadsCount,
            ':pl'        => $playsCount,
            ':consola'   => $consolaId,
            ':categoria' => $categoriaId,
        ]);
        $row = $stmt->fetch();
        return [
            'rank_descargas' => (int) $row['rank_descargas'],
            'rank_jugadas'   => (int) $row['rank_jugadas'],
            'total_consola'  => (int) $row['total_consola'],
            'total_genero'   => (int) $row['total_genero'],
        ];
    }

}