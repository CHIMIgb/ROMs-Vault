<?php
/**
 * scripts/backfill-slugs.php
 * Rellena juegos.slug y consolas.slug desde titulo/nombre (ítem 7.2).
 *
 * Idempotente: solo procesa filas con slug NULL o vacío, así que puede
 * ejecutarse varias veces sin efectos. Garantiza unicidad consultando los
 * slugs ya existentes (incluidos los rellenados en esta misma ejecución).
 *
 * Uso (Windows):
 *   C:\xampp\php\php.exe scripts\backfill-slugs.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/config/Slug.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

/**
 * Rellena la columna slug de una tabla desde una columna de texto.
 *
 * @return int nº de filas actualizadas
 */
function backfillSlugs(PDO $pdo, string $tabla, string $colTexto, string $fallback): int {
    // Slugs ya presentes (para no colisionar).
    $usados = $pdo->query("SELECT slug FROM {$tabla} WHERE slug IS NOT NULL AND slug <> ''")
                  ->fetchAll(PDO::FETCH_COLUMN);
    $usados = array_flip($usados);

    $rows = $pdo->query(
        "SELECT id, {$colTexto} AS texto FROM {$tabla} WHERE slug IS NULL OR slug = '' ORDER BY id"
    )->fetchAll();

    $upd = $pdo->prepare("UPDATE {$tabla} SET slug = ? WHERE id = ?");

    $n = 0;
    foreach ($rows as $r) {
        $base = Slug::make((string) $r['texto'], $fallback);

        $slug = $base;
        if (isset($usados[$slug])) {
            $i = 2;
            while (isset($usados[$base . '-' . $i])) {
                $i++;
            }
            $slug = $base . '-' . $i;
        }

        $upd->execute([$slug, (int) $r['id']]);
        $usados[$slug] = true;
        $n++;
    }

    return $n;
}

$nj = backfillSlugs($pdo, 'juegos',   'titulo', 'juego');
$nc = backfillSlugs($pdo, 'consolas', 'nombre', 'consola');

printf("OK — juegos: %d slug(s) rellenados; consolas: %d slug(s) rellenados.\n", $nj, $nc);
