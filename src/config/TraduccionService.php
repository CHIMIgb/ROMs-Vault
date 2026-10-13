<?php
require_once __DIR__ . '/../models/Traduccion.php';
require_once __DIR__ . '/translation/TranslateProviderFactory.php';
require_once __DIR__ . '/LoggerService.php';
require_once __DIR__ . '/i18n.php';

/**
 * config/TraduccionService.php
 * Resolución y generación de traducciones del contenido de BD (i18n, bloque 2).
 *
 * Dos responsabilidades:
 *
 *   1. RESOLUCIÓN (lectura pública): reemplaza los campos en 'es' por su
 *      traducción al idioma activo, con FALLBACK a 'es'. Siempre en lote
 *      (una sola consulta por colección) y con caché por request (anti N+1).
 *
 *   2. GENERACIÓN (auto-traducción): traduce al guardar contenido, de forma
 *      INCREMENTAL (solo si cambió el texto canónico, vía origen_hash) y
 *      respetando las correcciones manuales (`origen = 'manual'`).
 *
 * El camino de auto-traducción es fail-open: nunca lanza hacia el llamante.
 * Si no hay proveedor, el servicio falla o hay timeout, el guardado del
 * contenido continúa y este queda en 'es' hasta que se pueda traducir.
 */
class TraduccionService {

    private static ?Traduccion $model = null;

    /** @var array<string,array> Caché por request: "tabla:idioma" => [id => fila]. */
    private static array $cache = [];

    /** @var array<string,array> Ids ya cargados por clave: "tabla:idioma" => [id => true]. */
    private static array $cargados = [];

    // ── Resolución (lectura) ──────────────────────────────────────────────

    /**
     * Reemplaza nombre/descripcion de categorías por la traducción del idioma activo.
     * Los campos sin traducción conservan su valor en 'es'.
     */
    public static function resolverCategorias(array &$filas, ?string $idioma = null): void {
        $idioma = self::idiomaEfectivo($idioma);
        $ids    = self::ids($filas);
        if ($idioma === I18n::DEFAULT_LANG || $ids === []) {
            return;
        }

        $trad = self::mapa('categorias_traducciones', $idioma, $ids,
            static fn(array $faltan) => self::model()->getCategorias($faltan, $idioma));

        foreach ($filas as &$fila) {
            $id = (int) ($fila['id'] ?? 0);
            if (isset($trad[$id])) {
                $fila = self::aplicarCampos($fila, $trad[$id], ['nombre', 'descripcion']);
            }
        }
        unset($fila);
    }

    /**
     * Reemplaza la descripción de juegos por la traducción del idioma activo.
     */
    public static function resolverJuegos(array &$filas, ?string $idioma = null): void {
        $idioma = self::idiomaEfectivo($idioma);
        $ids    = self::ids($filas);
        if ($idioma === I18n::DEFAULT_LANG || $ids === []) {
            return;
        }

        $trad = self::mapa('juegos_traducciones', $idioma, $ids,
            static fn(array $faltan) => self::model()->getJuegos($faltan, $idioma));

        foreach ($filas as &$fila) {
            $id = (int) ($fila['id'] ?? 0);
            if (isset($trad[$id])) {
                $fila = self::aplicarCampos($fila, $trad[$id], ['descripcion']);
            }
        }
        unset($fila);
    }

    /**
     * Reemplaza `categoria_nombre` (nombre de categoría que llega por JOIN en
     * las filas de juegos) por su traducción, usando `categoria_id`.
     */
    public static function resolverCategoriasVinculadas(array &$filas, ?string $idioma = null): void {
        $idioma = self::idiomaEfectivo($idioma);
        if ($idioma === I18n::DEFAULT_LANG || $filas === []) {
            return;
        }

        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['categoria_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $trad = self::mapa('categorias_traducciones', $idioma, array_keys($ids),
            static fn(array $faltan) => self::model()->getCategorias($faltan, $idioma));

        foreach ($filas as &$fila) {
            $id = (int) ($fila['categoria_id'] ?? 0);
            $nombre = $trad[$id]['nombre'] ?? null;
            if (is_string($nombre) && trim($nombre) !== '') {
                $fila['categoria_nombre'] = $nombre;
            }
        }
        unset($fila);
    }

    // ── Generación (auto-traducción) ──────────────────────────────────────

    /**
     * Traduce una entidad a los idiomas destino (auto), de forma incremental y
     * sin pisar correcciones manuales. Nunca lanza.
     *
     * @param string $entidad 'categoria' | 'juego'
     * @param int    $id      Id de la entidad.
     * @param array  $campos  Campos canónicos en 'es' (p. ej. ['nombre'=>'Acción','descripcion'=>'...'])
     * @param array|null $targets Idiomas destino; null = TRANSLATE_TARGETS o todos menos 'es'.
     * @param bool   $dryRun  Si es true, solo calcula los idiomas pendientes (no llama al
     *                        proveedor ni escribe). Permite previsualizar el backfill.
     * @return array{ok:bool,motivo?:string,idiomas:array<int,string>,pendientes?:array<int,string>,dry_run?:bool,errores?:int,proveedor?:string}
     */
    public static function traducirEntidad(string $entidad, int $id, array $campos, ?array $targets = null, bool $dryRun = false): array {
        $entidad = strtolower($entidad);
        if (!in_array($entidad, ['categoria', 'juego'], true)) {
            return ['ok' => false, 'motivo' => 'entidad_invalida', 'idiomas' => []];
        }

        // Solo se traduce lo que tiene contenido (no gastar llamadas en vacíos).
        $campos = array_filter(
            $campos,
            static fn($v) => is_string($v) && trim($v) !== ''
        );
        if ($campos === []) {
            return ['ok' => false, 'motivo' => 'sin_texto', 'idiomas' => []];
        }

        $proveedor = TranslateProviderFactory::fromEnv();
        // En dry-run no hace falta proveedor: solo se evalúan los pendientes.
        if (!$dryRun && !$proveedor->isConfigured()) {
            return ['ok' => false, 'motivo' => 'no_configurado', 'idiomas' => [], 'proveedor' => $proveedor->name()];
        }

        $targets = self::targets($targets);
        if ($targets === []) {
            return ['ok' => false, 'motivo' => 'sin_idiomas', 'idiomas' => []];
        }

        $existentes = $entidad === 'categoria'
            ? self::model()->idiomasDeCategoria($id)
            : self::model()->idiomasDeJuego($id);

        $hash = self::hash($campos);
        $traducidos = [];
        $pendientes = [];
        $errores = 0;

        foreach ($targets as $target) {
            $prev = $existentes[$target] ?? null;

            // Nunca pisar una corrección manual.
            if ($prev !== null && ($prev['origen'] ?? '') === 'manual') {
                continue;
            }
            // Sin cambios en el texto canónico: nada que hacer (incremental por hash).
            if ($prev !== null
                && ($prev['origen'] ?? '') === 'auto'
                && (string) ($prev['origen_hash'] ?? '') === $hash) {
                continue;
            }

            if ($dryRun) {
                $pendientes[] = $target;
                continue;
            }

            try {
                $resultado = $proveedor->translateBatch($campos, I18n::DEFAULT_LANG, $target);
            } catch (\Throwable $e) {
                $errores++;
                LoggerService::write('traduccion_error', [
                    'entidad'   => $entidad,
                    'id'        => $id,
                    'idioma'    => $target,
                    'proveedor' => $proveedor->name(),
                    'error'     => $e->getMessage(),
                ], 'warning');
                continue;
            }

            if ($entidad === 'categoria') {
                self::model()->upsertCategoria(
                    $id, $target,
                    $resultado['nombre'] ?? null,
                    $resultado['descripcion'] ?? null,
                    'auto', false, $hash
                );
            } else {
                self::model()->upsertJuego(
                    $id, $target,
                    $resultado['descripcion'] ?? null,
                    'auto', false, $hash
                );
            }
            $traducidos[] = $target;
        }

        return [
            'ok'         => true,
            'idiomas'    => $traducidos,
            'pendientes' => $pendientes,
            'dry_run'    => $dryRun,
            'errores'    => $errores,
            'proveedor'  => $proveedor->name(),
        ];
    }

    /** Nombre del proveedor configurado (para la UI del admin). */
    public static function nombreProveedor(): string {
        return TranslateProviderFactory::fromEnv()->name();
    }

    /** ¿Hay proveedor configurado y listo para traducir? (para la UI del admin). */
    public static function proveedorConfigurado(): bool {
        return TranslateProviderFactory::fromEnv()->isConfigured();
    }

    /**
     * Idiomas destino efectivos: los indicados, o TRANSLATE_TARGETS, o todos
     * los idiomas de la UI menos 'es'. Siempre dentro de I18n::LANGUAGES.
     * @return array<int,string>
     */
    public static function targets(?array $targets = null): array {
        if ($targets !== null) {
            $lista = $targets;
        } else {
            $env = trim((string) ($_ENV['TRANSLATE_TARGETS'] ?? getenv('TRANSLATE_TARGETS') ?: ''));
            $lista = $env !== '' ? array_map('trim', explode(',', $env)) : I18n::LANGUAGES;
        }
        $lista = array_values(array_intersect($lista, I18n::LANGUAGES));
        return array_values(array_diff($lista, [I18n::DEFAULT_LANG]));
    }

    /** Hash estable del texto canónico (para la detección incremental de cambios). */
    public static function hash(array $campos): string {
        ksort($campos);
        $json = json_encode($campos, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json === false ? '' : $json);
    }

    /** Limpia las cachés en memoria (uso exclusivo en tests). */
    public static function resetCache(): void {
        self::$cache     = [];
        self::$cargados  = [];
        self::$model     = null;
    }

    // ── Internos ──────────────────────────────────────────────────────────

    private static function model(): Traduccion {
        if (self::$model === null) {
            self::$model = new Traduccion();
        }
        return self::$model;
    }

    /** Idioma efectivo: el indicado o el activo, validado contra la whitelist. */
    private static function idiomaEfectivo(?string $idioma): string {
        $idioma = $idioma ?? I18n::getLang();
        return in_array($idioma, I18n::LANGUAGES, true) ? $idioma : I18n::DEFAULT_LANG;
    }

    /** Ids únicos y válidos de un conjunto de filas (buscando la clave 'id'). */
    private static function ids(array $filas): array {
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }

    /** Aplica los campos traducidos no vacíos sobre la fila (fallback implícito a 'es'). */
    private static function aplicarCampos(array $fila, array $trad, array $campos): array {
        foreach ($campos as $campo) {
            $valor = $trad[$campo] ?? null;
            if (is_string($valor) && trim($valor) !== '') {
                $fila[$campo] = $valor;
            }
        }
        return $fila;
    }

    /**
     * Devuelve las traducciones de un conjunto de ids, cargando en lote solo lo
     * que falte (caché por request). Evita el N+1 en los listados.
     *
     * @param callable(array<int,int>):array $fetch
     * @return array<int,array>
     */
    private static function mapa(string $tabla, string $idioma, array $ids, callable $fetch): array {
        $claveBase = $tabla . ':' . $idioma;

        $faltan = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && empty(self::$cargados[$claveBase][$id])) {
                $faltan[$id] = true;
            }
        }

        if ($faltan !== []) {
            self::$cache[$claveBase] = (self::$cache[$claveBase] ?? []) + $fetch(array_keys($faltan));
            foreach (array_keys($faltan) as $id) {
                self::$cargados[$claveBase][$id] = true;
            }
        }

        $salida = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && isset(self::$cache[$claveBase][$id])) {
                $salida[$id] = self::$cache[$claveBase][$id];
            }
        }
        return $salida;
    }
}
