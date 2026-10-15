<?php
/**
 * scripts/normalizar-idiomas.php
 * Normaliza el campo `juegos.idiomas` de los juegos YA registrados.
 *
 * El campo se llenó a mano a lo largo del tiempo y acumula erratas y variantes
 * ("Ingles"/"Inglés", "Frances"/"Francés", "Japones"/"Japonese"/"Japoneses") y
 * valores basura ("NULL", "11"). Este script los deja en la lista canónica
 * separada por comas (Español, Inglés, Francés, ...) usando IdiomasNormalizer.
 *
 * Es IDEMPOTENTE: ejecutarlo dos veces no cambia nada la segunda vez.
 * No toca otros campos del juego.
 *
 * Uso (Windows):
 *   C:\xampp\php\php.exe scripts\normalizar-idiomas.php [--dry-run] [--limite=N]
 *
 * Opciones:
 *   --dry-run   Muestra los cambios sin escribir en la BD.
 *   --limite=N  Procesa como máximo N juegos.
 *   --ayuda     Muestra esta ayuda.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // Solo CLI: nunca expuesto por HTTP.
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/config/IdiomasNormalizer.php';

// ── Argumentos ────────────────────────────────────────────────────────────
$dryRun = false;
$limite = null;

$ayuda = static function (): void {
    echo <<<TXT
Normalización de juegos.idiomas (i18n, bloque 2)

Uso: php scripts/normalizar-idiomas.php [opciones]

  --dry-run   Muestra los cambios sin escribir en la BD.
  --limite=N  Procesa como máximo N juegos.
  --ayuda     Muestra esta ayuda.

TXT;
};

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--ayuda' || $arg === '--help' || $arg === '-h') {
        $ayuda();
        exit(0);
    } elseif (str_starts_with($arg, '--limite=')) {
        $limite = max(0, (int) substr($arg, 9));
    } else {
        fwrite(STDERR, "Opción desconocida: {$arg}\n");
        exit(2);
    }
}

// ── Conexión ──────────────────────────────────────────────────────────────
$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

echo "== Normalización de juegos.idiomas ==\n";
printf("Modo: %s\n\n", $dryRun ? 'DRY-RUN (sin escribir)' : 'REAL');

$limitSql = $limite !== null ? ' LIMIT ' . $limite : '';

try {
    $juegos = $pdo->query(
        "SELECT id, titulo, idiomas FROM juegos ORDER BY id{$limitSql}"
    )->fetchAll();
} catch (PDOException $e) {
    fwrite(STDERR, "Error consultando juegos: " . $e->getMessage() . "\n");
    exit(1);
}

$cambios   = 0;
$sinCambio = 0;
$vacios    = 0;
$errores   = 0;
$update    = $pdo->prepare("UPDATE juegos SET idiomas = :idiomas WHERE id = :id");

foreach ($juegos as $juego) {
    $id     = (int) $juego['id'];
    $actual = $juego['idiomas'] !== null ? (string) $juego['idiomas'] : null;
    $nuevo  = IdiomasNormalizer::normalizar($actual);

    if (trim((string) $actual) === '') {
        $vacios++;
        continue;
    }

    if ($nuevo === $actual) {
        $sinCambio++;
        continue;
    }

    $cambios++;
    printf("  #%-5d %-45s\n", $id, mb_strimwidth((string) $juego['titulo'], 0, 45, '…'));
    printf("         antes:', %s\n", $actual ?? '(null)');
    printf("         ahora: '%s'\n", $nuevo ?? '(null)');

    if ($dryRun) {
        continue;
    }

    try {
        $update->bindValue(':idiomas', $nuevo, $nuevo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $update->bindValue(':id', $id, PDO::PARAM_INT);
        $update->execute();
    } catch (PDOException $e) {
        $errores++;
        fwrite(STDERR, "  Error actualizando #{$id}: " . $e->getMessage() . "\n");
    }
}

echo "\n--- Resumen ---\n";
printf("Juegos leídos     : %d\n", count($juegos));
printf("Con cambios       : %d\n", $cambios);
printf("Ya normalizados   : %d\n", $sinCambio);
printf("Sin idiomas       : %d\n", $vacios);
printf("Errores           : %d\n", $errores);
if ($dryRun && $cambios > 0) {
    echo "Ejecuta sin --dry-run para aplicar los cambios.\n";
}

exit($errores > 0 ? 1 : 0);
