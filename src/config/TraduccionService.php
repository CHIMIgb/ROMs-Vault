<?php
require_once __DIR__ . '/../models/Traduccion.php';
require_once __DIR__ . '/translation/TranslateProviderFactory.php';
require_once __DIR__ . '/LoggerService.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/IdiomasNormalizer.php';

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
     * Reemplaza la descripción y los idiomas de juegos por la traducción del
     * idioma activo. Los campos sin traducción conservan su valor en 'es'.
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
                $fila = self::aplicarCampos($fila, $trad[$id], ['descripcion', 'idiomas']);
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
        $campos = self::limpiarCampos($campos);
        if ($campos === []) {
            return ['ok' => false, 'motivo' => 'sin_texto', 'idiomas' => []];
        }

        $targets = self::targets($targets);
        if ($targets === []) {
            return ['ok' => false, 'motivo' => 'sin_idiomas', 'idiomas' => []];
        }

        $existentes = $entidad === 'categoria'
            ? self::model()->idiomasDeCategoria($id)
            : self::model()->idiomasDeJuego($id);

        return self::generar(
            $entidad,
            $id,
            $existentes,
            $campos,   // hash sobre el conjunto canónico completo
            $campos,   // se traducen todos los campos
            $targets,
            $dryRun,
            static function (string $target, array $resultado, string $hash) use ($entidad, $id): void {
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
                        'auto', false, $hash,
                        self::normalizarListaIdiomas($resultado['idiomas'] ?? null)
                    );
                }
            }
        );
    }

    /**
     * Re-genera SOLO el campo `idiomas` de las traducciones de un juego, sin
     * re-traducir la descripción ya guardada (backfill de idiomas).
     *
     * Recibe los campos canónicos COMPLETOS (descripcion + idiomas) para que el
     * `origen_hash` sea el mismo que usa la vía unificada (traducirEntidad /
     * guardado desde el admin): así la fila queda marcada como sincronizada y
     * las re-ejecuciones son idempotentes. La descripción NO se toca.
     *
     * @param array $campos Campos canónicos en 'es' (['descripcion'=>..., 'idiomas'=>...]).
     * @return array{ok:bool,motivo?:string,idiomas:array<int,string>,pendientes?:array<int,string>,dry_run?:bool,errores?:int,proveedor?:string}
     */
    public static function traducirIdiomasJuego(int $id, array $campos, ?array $targets = null, bool $dryRun = false): array {
        $campos  = self::limpiarCampos($campos);
        $idiomas = trim((string) ($campos['idiomas'] ?? ''));
        if ($idiomas === '') {
            return ['ok' => false, 'motivo' => 'sin_texto', 'idiomas' => []];
        }

        $targets = self::targets($targets);
        if ($targets === []) {
            return ['ok' => false, 'motivo' => 'sin_idiomas', 'idiomas' => []];
        }

        $existentes = self::model()->idiomasDeJuego($id);

        return self::generar(
            'juego',
            $id,
            $existentes,
            $campos,                 // hash sobre el conjunto canónico completo
            ['idiomas' => $idiomas], // solo se traduce (y escribe) `idiomas`
            $targets,
            $dryRun,
            static function (string $target, array $resultado, string $hash) use ($id): void {
                self::model()->upsertJuegoIdiomas(
                    $id, $target,
                    self::normalizarListaIdiomas($resultado['idiomas'] ?? null),
                    'auto', false, $hash
                );
            }
        );
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

    /**
     * Traduce un conjunto de campos canónicos a un idioma destino.
     *
     * El campo `idiomas` (lista de idiomas disponibles) se envía como UNA sola
     * cadena con los tokens unidos por " / ". Así el proveedor traduce con
     * contexto (calidad alta en todos los idiomas: enviar cada idioma aislado
     * produce errores como "Inglés" -> "日本語 English" o "联合国") y, verificado
     * contra LibreTranslate, conserva el delimitador "/" en los 11 idiomas. Al
     * volver, se divide por "/" y se re-une con ", " para que las vistas pinten
     * los chips separados en todos los idiomas (evita además el problema del
     * separador propio de ja/zh `、` y del árabe, que une con `و`).
     *
     * Todo se envía en el MISMO lote: sigue siendo UNA sola petición.
     *
     * @internal Expuesto (estático) para poder testearlo con un proveedor falso.
     *
     * @param array<string,string> $campos Campos en 'es' (p. ej. descripcion, idiomas).
     * @return array<string,string> Mismos campos con los valores traducidos.
     */
    public static function traducirCampos(TranslationProvider $proveedor, array $campos, string $target): array {
        $tokens = [];
        $enviar = [];
        foreach ($campos as $clave => $valor) {
            if ($clave === 'idiomas') {
                $tokens = IdiomasNormalizer::tokens((string) $valor);
                if ($tokens !== []) {
                    $enviar['idiomas'] = implode(' / ', $tokens);
                }
            } else {
                $enviar[$clave] = $valor;
            }
        }

        $res = $proveedor->translateBatch($enviar, I18n::DEFAULT_LANG, $target);

        $salida = $campos;
        foreach ($campos as $clave => $valor) {
            if ($clave === 'idiomas') {
                if ($tokens === []) {
                    continue;
                }
                $traducido = $res['idiomas'] ?? null;
                if (!is_string($traducido) || trim($traducido) === '') {
                    // Sin respuesta: conservar el original normalizado (fallback).
                    $salida['idiomas'] = self::normalizarListaIdiomas($valor);
                    continue;
                }
                // Re-separa por el delimitador (y normaliza separadores locales).
                $partes = array_filter(
                    array_map('trim', explode('/', $traducido)),
                    static fn($p) => $p !== ''
                );
                $salida['idiomas'] = self::normalizarListaIdiomas(implode(', ', $partes));
            } elseif (isset($res[$clave]) && is_string($res[$clave]) && $res[$clave] !== '') {
                $salida[$clave] = $res[$clave];
            }
        }
        return $salida;
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

    /** Descarta campos vacíos o no-string (no gastar llamadas en vacíos). */
    private static function limpiarCampos(array $campos): array {
        return array_filter(
            $campos,
            static fn($v) => is_string($v) && trim($v) !== ''
        );
    }

    /**
     * Núcleo de GENERACIÓN: recorre los idiomas destino saltando lo manual y lo
     * ya sincronizado (origen='auto' con el mismo hash), traduce e invoca
     * $escribir. Compartido por traducirEntidad() (todos los campos) y
     * traducirIdiomasJuego() (solo `idiomas`), para no duplicar la lógica de
     * incrementalidad ni la de respeto a las correcciones manuales.
     *
     * @param array<string,array> $existentes        idioma => fila de traducción actual.
     * @param array<string,string> $camposCanonicos   Campos fuente para el hash.
     * @param array<string,string> $camposTraducir    Campos que realmente se traducen.
     * @param callable(string,array,string):void $escribir (target, resultado, hash)
     * @return array{ok:bool,motivo?:string,idiomas:array<int,string>,pendientes:array<int,string>,dry_run:bool,errores:int,proveedor:string}
     */
    private static function generar(
        string $entidad,
        int $id,
        array $existentes,
        array $camposCanonicos,
        array $camposTraducir,
        array $targets,
        bool $dryRun,
        callable $escribir
    ): array {
        $proveedor = TranslateProviderFactory::fromEnv();
        // En dry-run no hace falta proveedor: solo se evalúan los pendientes.
        if (!$dryRun && !$proveedor->isConfigured()) {
            return [
                'ok'         => false,
                'motivo'     => 'no_configurado',
                'idiomas'    => [],
                'pendientes' => [],
                'dry_run'    => $dryRun,
                'errores'    => 0,
                'proveedor'  => $proveedor->name(),
            ];
        }

        $hash       = self::hash($camposCanonicos);
        $traducidos = [];
        $pendientes = [];
        $errores    = 0;

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
                $resultado = self::traducirCampos($proveedor, $camposTraducir, $target);
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

            $escribir($target, $resultado, $hash);
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
     * Normaliza la lista de idiomas traducida a un formato estable para las
     * vistas: separadores tipo lista de otros idiomas (、，；،, ; /) pasan a "," y
     * las partes se limpian y unen con ", ". Así el catálogo y la ficha pintan
     * los chips igual en todos los idiomas.
     */
    private static function normalizarListaIdiomas(?string $texto): ?string {
        if ($texto === null || trim($texto) === '') {
            return null;
        }
        $texto   = str_replace(['、', '，', '；', '،', ';', '/'], ',', $texto);
        $partes  = array_filter(array_map('trim', explode(',', $texto)), static fn($p) => $p !== '');
        return $partes === [] ? null : implode(', ', $partes);
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
