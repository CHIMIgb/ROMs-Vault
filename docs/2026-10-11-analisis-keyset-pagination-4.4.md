# Análisis y descarte del ítem 4.4 — Keyset pagination

**Fecha:** 2026-10-11
**Rama:** `docs/descarte-4.4-keyset-pagination`
**Ítem del roadmap:** [4.4 Keyset pagination](_archivo/2026-10-07-plan-mejoras-seguridad-y-rendimiento.md)
**Estado:** Descartado / diferido (no aplicable al estado actual del proyecto)

## Contexto

El ítem 4.4 del plan de seguridad y rendimiento planteaba, *como fase futura*, sustituir la
paginación por `OFFSET` por **keyset pagination** (`WHERE j.id < :cursor ORDER BY id DESC LIMIT n`)
con índices cubrientes, para "un catálogo grande".

Antes de implementarlo se levantaron dos dudas de diseño:

1. **La UI usa paginación numerada** (`Pagination::render` genera enlaces `&page=N`, primera,
   última y ±2, con "página X de Y"). El keyset es **incompatible** con la paginación numerada:
   no permite "saltar a la página N" sin un `OFFSET`.
2. **El orden por defecto del catálogo es `random`** (`md5(j.id::text || :seed)`, `Juego::resolveOrder`),
   un orden **no indexable**. El keyset no puede aprovechar índice sobre esa expresión.

Para no decidir sobre suposiciones, se midió el rendimiento real de la paginación **actual**
(LIMIT/OFFSET) con el catálogo simulado a **2.000** y **200.000** juegos.

## Metodología

- **Entorno:** PHP 8.2 (XAMPP Windows, `C:\xampp\php\php.exe`), PostgreSQL **18.4** local,
  BD `roms-vault` (123 juegos reales + 16 consolas + 39 categorías).
- **Script:** `scratch/bench_pagination.php` (throwaway, gitignored; se conserva en el apéndice).
  - Siembra N juegos **dentro de una transacción** y hace **`ROLLBACK`** al terminar → la BD
    queda intacta (no deja filas). Verificado.
  - Ejecuta `ANALYZE juegos` tras sembrar para que el planner estime con estadísticas reales.
  - Invoca los **métodos reales del modelo** (`Juego::getWithRelationsPaginated`,
    `Juego::getAllPaginatedFiltered`, `Juego::countWithFilters`, `Juego::countAllFiltered`), no SQL
    inventado. Mide datos + COUNT = coste de una carga de página real.
  - Cronometra con `hrtime()`: 1 *warmup* + **5 ejecuciones**, y reporta la **mediana**.
  - Fija `$_COOKIE['rv_cat_seed']` (evita `setcookie()` en CLI; mismo formato que producción).

## Resultados

### Con 2.123 juegos (2.000 sembrados + 123 reales) — ~107 páginas

| Escenario | datos | COUNT | **TOTAL** |
|---|---:|---:|---:|
| Catálogo random — página 1 | 13,07 ms | 0,95 ms | **14,01 ms** |
| Catálogo random — página 50 | 27,59 ms | 1,48 ms | **29,08 ms** |
| Catálogo random — última página | 24,84 ms | 1,44 ms | **26,28 ms** |
| Catálogo recientes — última página | 10,16 ms | 1,20 ms | **11,37 ms** |
| Catálogo descargas — última página | 6,70 ms | 1,41 ms | **8,11 ms** |
| Catálogo filtro consola — offset 100 | 1,78 ms | 0,82 ms | **2,60 ms** |
| Catálogo búsqueda "Bench" — página 1 | 2,08 ms | 3,44 ms | **5,52 ms** |
| ADMIN id DESC — página 1 | 1,49 ms | 1,35 ms | **2,84 ms** |
| ADMIN id DESC — última página | 3,98 ms | 0,63 ms | **4,62 ms** |
| ADMIN id DESC + búsqueda — página 1 | 1,45 ms | 2,82 ms | **4,27 ms** |

### Con 200.123 juegos (200.000 sembrados + 123 reales) — ~10.007 páginas

| Escenario | datos | COUNT | **TOTAL** |
|---|---:|---:|---:|
| Catálogo random — página 1 | 511,37 ms | 111,41 ms | **622,78 ms** |
| Catálogo random — página 50 | 639,66 ms | 150,16 ms | **789,82 ms** |
| Catálogo random — última página | 3.768,65 ms | 146,95 ms | **3.915,60 ms** |
| Catálogo recientes — última página | 470,94 ms | 137,10 ms | **608,04 ms** |
| Catálogo descargas — última página | 597,74 ms | 139,88 ms | **737,62 ms** |
| Catálogo filtro consola — offset 100 | 3,19 ms | 22,02 ms | **25,22 ms** |
| Catálogo búsqueda "Bench" — página 1 | 2,51 ms | 246,99 ms | **249,50 ms** |
| ADMIN id DESC — página 1 | 2,42 ms | 131,71 ms | **134,13 ms** |
| ADMIN id DESC — última página | 463,18 ms | 130,43 ms | **593,61 ms** |
| ADMIN id DESC + búsqueda — página 1 | 2,58 ms | 240,98 ms | **243,55 ms** |

## Planes EXPLAIN ANALYZE (a 200.000, recortados)

**Orden `random` (`md5(id||seed)`) — el más caro, no indexable:**

```
-> Limit  (actual time=3855..3902 rows=20)
     -> Sort  (Sort Key: md5(((j.id)::text || seed)))
           Sort Method: external merge  Disk: 15880kB
           -> Parallel Seq Scan on juegos j  (rows=200120)
Execution Time: 3921 ms
```

**Orden `recientes` (`idx_juegos_activo_creado`, offset profundo) — recorre el índice entero:**

```
-> Index Scan using idx_juegos_activo_creado on juegos j  (rows=200120)
Execution Time: 702 ms
```

**Admin `id DESC` (`juegos_pkey` Backward, offset profundo) — el caso que el keyset sí mejoraría:**

```
-> Index Scan Backward using juegos_pkey on juegos j  (rows=200120)
Execution Time: 614 ms
```

## Análisis

1. **A 123 juegos (escenario real actual):** la carga de cualquier página es < 5 ms. El keyset
   no aporta nada.
2. **A 2.000 juegos:** todo < 30 ms. El keyset no aporta nada.
3. **A 200.000 juegos:** el `OFFSET` profundo **sí degrada de forma lineal** en los listados
   ordenables por índice (admin `id DESC`: 2,4 ms → 463 ms; recientes: → 471 ms). **Aquí el keyset
   sí ayudaría** (`WHERE id < :cursor` evita recorrer 200.000 entradas → volvería a ~3 ms).
4. **Pero el keyset no toca los dos costes dominantes**, que son los que más pesan en el catálogo:
   - **Orden `random`** (`md5(id||seed)`): el `Sort` calcula el hash de todas las filas y las ordena
     en disco (`external merge`, 3,9 s en la última página). **No es indexable** → el keyset seguiría
     necesitando el mismo `Sort` completo (o más complejo con cursor `(hash, id)`), **sin mejora**.
   - **El `COUNT`** de la paginación (`countWithFilters`): 110–247 ms a 200k. La UI necesita el total
     para "mostrando X de Y" y los números de página; el keyset **no lo elimina**.
5. **Coste de la alternativa:** el keyset obliga a abandonar la paginación numerada (Anterior/Siguiente
   con cursor), cambiar los enlaces `?page=N` y los endpoints AJAX (`ajax_catalog.php`,
   `ajax_admin.php`) y sus `bindPagination()`.

## Decisión

**Descartar (diferir) el ítem 4.4.** La migración a keyset:

- No aporta **ningún** beneficio al volumen actual (123 juegos) ni a un volumen 16× mayor (2.000).
- Solo ayudaría en listados **ordenados por índice** y a **escala grande (> ~10.000 filas)**; el
  argumento no aplica al orden `random` (por defecto del catálogo) ni al `COUNT`.
- Implicaría **perder funcionalidad** que sí se usa (paginación numerada + salto directo), a cambio
  de resolver una parte del problema que hoy no existe.

**Criterio de reactivación** (si algún día se reconsidera): catálogo de **> ~10.000 juegos**, con
orden por `id`/`created_at` (no `random`), y aceptando la navegación secuencial por cursor.

## Verificación

- El benchmark se ejecutó dos veces (N=2000 y N=200000). En ambos casos, tras el `ROLLBACK`:
  `SELECT COUNT(*) FROM juegos` → **123** (BD intacta). El script imprime `BD INTACTA OK`.
- La siembra es transaccional; ante cualquier error, `finally` revierte (`rollback` verificado
  también en el primer intento, que falló por `region VARCHAR(10)`).

## Reproducción (script usado)

`scratch/bench_pagination.php` es *throwaway* (gitignored). Se copia aquí para conservar la
evidencia. Variables opcionales: `BENCH_N` (def. 2000), `BENCH_RUNS` (def. 5).

```php
<?php
/**
 * scratch/bench_pagination.php
 * Benchmark de la paginación ACTUAL (LIMIT/OFFSET) del catálogo público y del
 * admin, con N juegos simulados. Throwaway: vive en scratch/ (gitignored).
 *
 * Siembra N juegos DENTRO DE UNA TRANSACCIÓN y hace ROLLBACK al terminar: la
 * BD local queda intacta (no deja filas). Usa los métodos REALES del modelo
 * (Juego::getWithRelationsPaginated / getAllPaginatedFiltered / countWithFilters
 * / countAllFiltered) para medir el coste de una carga de página real
 * (consulta de datos + COUNT).
 *
 * Uso:
 *   C:\xampp\php\php.exe scratch\bench_pagination.php
 * Variables opcionales:
 *   BENCH_N=2000     nº de juegos a sembrar
 *   BENCH_RUNS=5     repeticiones por escenario
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/models/Model.php';
require_once $root . '/src/models/Juego.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

$N        = max(1, (int) (getenv('BENCH_N') ?: 2000));
$RUNS     = max(1, (int) (getenv('BENCH_RUNS') ?: 5));
$LIMIT    = 20; // items por página hardcodeado en el proyecto (4x5)
$SEED     = '0123456789abcdef0123456789abcdef';

// Seed fija del catálogo: evita setcookie() en CLI (headers ya "enviados").
// En producción HomeController usa Juego::getCatalogSeed() (cookie) con el
// mismo formato de 32 hex.
$_COOKIE['rv_cat_seed'] = $SEED;

$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

$realCount = (int) $pdo->query('SELECT COUNT(*) FROM juegos')->fetchColumn();

echo str_repeat('=', 84), "\n";
echo "BENCHMARK DE PAGINACION — LIMIT/OFFSET (estado actual)\n";
echo str_repeat('=', 84), "\n";
printf("PostgreSQL        : %s\n", $pdo->query('SHOW server_version')->fetchColumn());
printf("Base de datos     : %s\n", $pdo->query('SELECT current_database()')->fetchColumn());
printf("Juegos reales     : %s\n", number_format($realCount));
printf("Juegos sembrados  : %s\n", number_format($N));
printf("Items por pagina  : %d\n", $LIMIT);
printf("Runs por escenario: %d (+1 warmup)\n", $RUNS);
echo "\n";

/**
 * Cronometra una llamada y devuelve estadísticas en milisegundos.
 *
 * @return array{min:float,med:float,avg:float,max:float,rows:int}
 */
function bench(callable $fn, int $runs): array {
    $times = [];
    $rows  = 0;
    $fn(); // warmup (descarta)
    for ($r = 0; $r < $runs; $r++) {
        $t0  = hrtime(true);
        $res = $fn();
        $t1  = hrtime(true);
        $times[] = ($t1 - $t0) / 1e6; // ns -> ms
        if (is_array($res)) {
            $rows = count($res);
        } elseif (is_int($res)) {
            $rows = $res;
        }
    }
    sort($times);
    return [
        'min'  => $times[0],
        'med'  => $times[intdiv(count($times), 2)],
        'avg'  => array_sum($times) / count($times),
        'max'  => end($times),
        'rows' => $rows,
    ];
}

$pdo->beginTransaction();
$exitCode = 0;

try {
    // ── 1. Siembra sintética ─────────────────────────────────────────────
    $consolas   = $pdo->query('SELECT id FROM consolas  ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $categorias = $pdo->query('SELECT id FROM categorias ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

    if (!$consolas || !$categorias) {
        throw new RuntimeException('Faltan consolas/categorias base para las FKs.');
    }

    $regiones = ['PAL', 'NTSC', 'NTSC-J', 'FREE'];
    $ins = $pdo->prepare(
        'INSERT INTO juegos
            (titulo, imagen, descripcion, consola_id, categoria_id, region,
             fecha_lanzamiento, idiomas, formato_imagen, game_id_code,
             google_drive_file_id, google_drive_view_link, size_bytes,
             downloads_count, plays_count, activo, created_at, updated_at)
         VALUES
            (:titulo, :imagen, :descripcion, :consola_id, :categoria_id, :region,
             :fecha_lanzamiento, :idiomas, :formato_imagen, :game_id_code,
             :file_id, :view_link, :size_bytes,
             :downloads, :plays, true, :created_at, :updated_at)'
    );

    $nConsolas   = count($consolas);
    $nCategorias = count($categorias);
    $nRegiones   = count($regiones);
    $base        = time();

    for ($i = 1; $i <= $N; $i++) {
        $num   = str_pad((string) $i, 5, '0', STR_PAD_LEFT);
        $fecha = sprintf('%04d-%02d-%02d 00:00:00', 1990 + ($i % 35), 1 + ($i % 12), 1 + ($i % 28));
        $creado = date('Y-m-d H:i:s', $base - $i * 60);
        $ins->execute([
            ':titulo'            => 'Bench Game ' . $num,
            ':imagen'            => '',
            ':descripcion'       => 'Fila sintetica de benchmark ' . $num,
            ':consola_id'        => $consolas[$i % $nConsolas],
            ':categoria_id'      => $categorias[$i % $nCategorias],
            ':region'            => $regiones[$i % $nRegiones],
            ':fecha_lanzamiento' => $fecha,
            ':idiomas'           => 'Español, Inglés',
            ':formato_imagen'    => 'ISO',
            ':game_id_code'      => 'BENCH-' . $num,
            ':file_id'           => 'BENCHFILE' . $num,
            ':view_link'         => 'https://drive.google.com/file/d/BENCHFILE' . $num,
            ':size_bytes'        => 100000000 + $i * 137,
            ':downloads'         => $i % 500,
            ':plays'             => $i % 300,
            ':created_at'        => $creado,
            ':updated_at'        => $creado,
        ]);
    }

    $pdo->exec('ANALYZE juegos');

    $total = (int) $pdo->query('SELECT COUNT(*) FROM juegos WHERE activo = true')->fetchColumn();
    printf(
        "Sembrados %s filas. Total activos: %s (~%s paginas de %d)\n\n",
        number_format($N),
        number_format($total),
        number_format((int) ceil($total / $LIMIT)),
        $LIMIT
    );

    $juego = new Juego();

    // ── 2. Escenarios ────────────────────────────────────────────────────
    // offset de la "ultima pagina" del total simulado
    $deepOffset = intdiv($total, $LIMIT) * $LIMIT - $LIMIT; // ~ultima pagina

    $scenarios = [
        'Catalogo random — pagina 1'        => ['kind' => 'cat', 'filters' => ['orden' => 'random'],    'offset' => 0],
        'Catalogo random — pagina 50'       => ['kind' => 'cat', 'filters' => ['orden' => 'random'],    'offset' => 980],
        'Catalogo random — ultima pagina'   => ['kind' => 'cat', 'filters' => ['orden' => 'random'],    'offset' => $deepOffset],
        'Catalogo recientes — ultima pagina'=> ['kind' => 'cat', 'filters' => ['orden' => 'recientes'], 'offset' => $deepOffset],
        'Catalogo descargas — ultima pagina'=> ['kind' => 'cat', 'filters' => ['orden' => 'descargas'], 'offset' => $deepOffset],
        'Catalogo filtro consola — offset 100' => ['kind' => 'cat', 'filters' => ['consola' => $consolas[1] ?? 1], 'offset' => 100],
        'Catalogo busqueda "Bench" — pagina 1' => ['kind' => 'cat', 'filters' => ['busqueda' => 'Bench'], 'offset' => 0],
        'ADMIN id DESC — pagina 1'          => ['kind' => 'admin', 'filters' => [],                  'offset' => 0],
        'ADMIN id DESC — ultima pagina'     => ['kind' => 'admin', 'filters' => [],                  'offset' => $deepOffset],
        'ADMIN id DESC + busqueda — pagina 1' => ['kind' => 'admin', 'filters' => ['busqueda' => 'Bench'], 'offset' => 0],
    ];

    $rowsOut = [];
    foreach ($scenarios as $label => $s) {
        if ($s['kind'] === 'cat') {
            $dataFn  = fn() => $juego->getWithRelationsPaginated($s['filters'], $s['offset'], $LIMIT, $SEED);
            $countFn = fn() => $juego->countWithFilters($s['filters']);
        } else {
            $dataFn  = fn() => $juego->getAllPaginatedFiltered($s['filters'], $s['offset'], $LIMIT);
            $countFn = fn() => $juego->countAllFiltered($s['filters']);
        }

        $d = bench($dataFn, $RUNS);
        $c = bench($countFn, $RUNS);

        $rowsOut[] = [
            'label'      => $label,
            'data_med'   => $d['med'],
            'count_med'  => $c['med'],
            'total_med'  => $d['med'] + $c['med'],
            'data_min'   => $d['min'],
            'data_max'   => $d['max'],
            'rows'       => $d['rows'],
        ];
    }

    // ── 3. Tabla de resultados ───────────────────────────────────────────
    echo str_repeat('-', 84), "\n";
    printf("%-50s %8s %8s %8s %6s\n", 'Escenario', 'datos', 'COUNT', 'TOTAL', 'filas');
    echo str_repeat('-', 84), "\n";
    foreach ($rowsOut as $r) {
        printf(
            "%-50s %6.2fms %6.2fms %6.2fms %6d\n",
            $r['label'],
            $r['data_med'],
            $r['count_med'],
            $r['total_med'],
            $r['rows']
        );
    }
    echo str_repeat('-', 84), "\n";
    echo "(tiempos = mediana de {$RUNS} ejecuciones; TOTAL = consulta de datos + COUNT)\n\n";

    // ── 4. Planes EXPLAIN representativos ────────────────────────────────
    $planes = [
        'random'              => "SELECT j.*, c.nombre AS consola_nombre, c.emulacion_online AS consola_emulacion_online, cat.nombre AS categoria_nombre
                                  FROM juegos j
                                  LEFT JOIN consolas c ON j.consola_id = c.id
                                  LEFT JOIN categorias cat ON j.categoria_id = cat.id
                                  WHERE j.activo = true
                                  ORDER BY md5(j.id::text || '{$SEED}') LIMIT {$LIMIT} OFFSET {$deepOffset}",
        'recientes'           => "SELECT j.*, c.nombre AS consola_nombre, c.emulacion_online AS consola_emulacion_online, cat.nombre AS categoria_nombre
                                  FROM juegos j
                                  LEFT JOIN consolas c ON j.consola_id = c.id
                                  LEFT JOIN categorias cat ON j.categoria_id = cat.id
                                  WHERE j.activo = true
                                  ORDER BY j.created_at DESC LIMIT {$LIMIT} OFFSET {$deepOffset}",
        'admin (id DESC)'     => "SELECT j.*, c.nombre AS consola_nombre, cat.nombre AS categoria_nombre
                                  FROM juegos j
                                  LEFT JOIN consolas c ON j.consola_id = c.id
                                  LEFT JOIN categorias cat ON j.categoria_id = cat.id
                                  ORDER BY j.id DESC LIMIT {$LIMIT} OFFSET {$deepOffset}",
    ];

    echo str_repeat('=', 84), "\n";
    echo "PLANES DE EJECUCION (EXPLAIN ANALYZE, BUFFERS)\n";
    echo str_repeat('=', 84), "\n";
    foreach ($planes as $name => $sql) {
        echo "\n### orden: {$name}\n";
        $lines = $pdo->query('EXPLAIN (ANALYZE, BUFFERS) ' . $sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($lines as $line) {
            echo '  ', $line, "\n";
        }
    }
    echo "\n";

} catch (Throwable $e) {
    $exitCode = 1;
    fwrite(STDERR, "\n[ERROR] " . $e->getMessage() . "\n");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

// ── 5. Verificación: la BD debe quedar intacta ──────────────────────────
$after = (int) $pdo->query('SELECT COUNT(*) FROM juegos')->fetchColumn();
echo str_repeat('=', 84), "\n";
printf("ROLLBACK aplicado. Juegos tras el benchmark: %s (antes: %s) — %s\n",
    number_format($after),
    number_format($realCount),
    $after === $realCount ? 'BD INTACTA OK' : 'DIFERENCIA! revisar'
);
echo str_repeat('=', 84), "\n";

exit($exitCode);
```
