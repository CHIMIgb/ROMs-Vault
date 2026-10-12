<?php
/**
 * scripts/migrar-imagenes-r2.php
 * Migra las portadas/capturas de public/uploads/ a Cloudflare R2 y reescribe
 * las referencias (juegos.imagen, juegos.capturas) a la URL publica completa.
 *
 * Idempotente: las referencias que ya son URL http(s) se omiten; los archivos
 * que no existen en disco se avisan y se dejan intactos. Estructura de destino:
 *   {consola-slug}/{juego-slug}/portada.ext  y  captura-N.ext
 * (los archivos ya guardados en subcarpeta conservan su ruta tal cual).
 *
 * Uso (Windows):
 *   C:\xampp\php\php.exe scripts\migrar-imagenes-r2.php --dry-run
 *   C:\xampp\php\php.exe scripts\migrar-imagenes-r2.php --yes
 *   C:\xampp\php\php.exe scripts\migrar-imagenes-r2.php --yes --limit=20
 *
 * Requiere R2_PUBLIC_BASE en .env y, para migrar de verdad (sin --dry-run),
 * las credenciales R2 completas (R2_ENDPOINT/R2_BUCKET/R2_ACCESS_KEY_*).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/config/Slug.php';
require_once $root . '/src/config/StorageService.php';
require_once $root . '/src/models/Juego.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

$opts    = getopt('', ['dry-run', 'yes', 'limit::']);
$dryRun  = array_key_exists('dry-run', $opts);
$confirm = array_key_exists('yes', $opts);
$limit   = isset($opts['limit']) && is_string($opts['limit']) ? max(0, (int) $opts['limit']) : 0;

$publicBase = rtrim((string) ($_ENV['R2_PUBLIC_BASE'] ?? getenv('R2_PUBLIC_BASE')), '/');
if ($publicBase === '') {
    fwrite(STDERR, "Falta R2_PUBLIC_BASE (URL publica del bucket). Definelo en .env.\n");
    exit(1);
}

$storage = StorageService::make();
if (!$dryRun && !$storage->isRemote()) {
    fwrite(STDERR, "R2 no esta configurado (faltan credenciales). Completa .env antes de migrar.\n");
    exit(1);
}

$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

if (!$dryRun && !$confirm) {
    fwrite(STDERR, "Esto subira archivos a R2 y reescribira la BD. Reejecuta con --yes para confirmar.\n");
    exit(2);
}

const UPLOADS_PREFIX = 'public/uploads/';
$uploadsRoot = $root . '/public/uploads';

/** URL publica de una clave de objeto. */
function urlPublica(string $publicBase, string $key): string
{
    return $publicBase . '/' . ltrim($key, '/');
}

/** ¿La referencia ya apunta a un CDN (R2 u otro)? */
function estaRemota(string $ref): bool
{
    return (bool) preg_match('#^https?://#i', $ref);
}

/** Ruta relativa bajo public/uploads/, o null si no es una referencia local. */
function rutaBajoUploads(string $ref): ?string
{
    $rel = ltrim(str_replace('\\', '/', $ref), '/');
    if (strpos($rel, UPLOADS_PREFIX) !== 0) {
        return null;
    }
    $sub = substr($rel, strlen(UPLOADS_PREFIX));
    return $sub === '' ? null : $sub;
}

/** Content-Type por extension (R2 lo exige para servir correctamente). */
function mimePorExtension(string $ext): string
{
    $map = [
        'webp' => 'image/webp',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
    ];
    return $map[strtolower($ext)] ?? 'application/octet-stream';
}

// Prefijos ya ocupados en R2, para no pisar carpetas existentes al asignar
// nombres nuevos a los archivos sueltos (estructura antigua).
$prefijosOcupados = [];
if ($storage->isRemote()) {
    foreach ($storage->client()->listObjects('') as $k) {
        $pos = strrpos($k, '/');
        if ($pos !== false) {
            $prefijosOcupados[substr($k, 0, $pos)] = true;
        }
    }
}

$sql = "SELECT j.id, j.titulo, j.imagen, j.capturas, c.nombre AS consola
        FROM juegos j LEFT JOIN consolas c ON c.id = j.consola_id
        ORDER BY j.id";
$juegos = $pdo->query($sql)->fetchAll();
if ($limit > 0) {
    $juegos = array_slice($juegos, 0, $limit);
}

$upd = $pdo->prepare('UPDATE juegos SET imagen = ?, capturas = ? WHERE id = ?');
$reservados = [];

$totArchivos = 0;
$totOmitidos = 0;
$totErrores  = 0;
$totUpdate   = 0;

foreach ($juegos as $j) {
    $imagen   = (string) ($j['imagen'] ?? '');
    $capturas = Juego::parseCapturas($j['capturas'] ?? null);

    // Refres locales gestionables (ni remotas, ni vacias, ni formato desconocido).
    $locales = [];
    foreach (array_merge([$imagen], $capturas) as $ref) {
        if ($ref !== '' && !estaRemota((string) $ref) && rutaBajoUploads((string) $ref) !== null) {
            $locales[] = (string) $ref;
        }
    }
    if ($locales === []) {
        continue;
    }

    // Prefijo destino: si ya hay archivos en subcarpeta, se conserva su ruta;
    // si no (estructura antigua), {consola-slug}/{juego-slug} deduplicado.
    $prefijo = null;
    foreach ($locales as $ref) {
        $sub = rutaBajoUploads($ref);
        if ($sub !== null && strpos($sub, '/') !== false) {
            $prefijo = substr($sub, 0, strrpos($sub, '/'));
            break;
        }
    }
    if ($prefijo === null) {
        $base = Slug::make((string) ($j['consola'] ?? ''), 'consola') . '/' . Slug::make((string) $j['titulo'], 'juego');
        $prefijo = $base;
        $i = 2;
        while (isset($reservados[$prefijo]) || isset($prefijosOcupados[$prefijo])) {
            $prefijo = $base . '-' . $i;
            $i++;
        }
    }
    $reservados[$prefijo] = true;

    /**
     * Sube (o simula) una referencia y devuelve [nuevaRef, cambio?].
     * $nombreBase: 'portada' o 'captura-N' (sin extension).
     */
    $migrar = function (string $ref, string $nombreBase) use ($storage, $publicBase, $uploadsRoot, $prefijo, $dryRun, &$totArchivos, &$totOmitidos, &$totErrores): array {
        if ($ref === '' || estaRemota($ref)) {
            return [$ref, false];
        }
        $sub = rutaBajoUploads($ref);
        if ($sub === null) {
            $totOmitidos++;
            return [$ref, false];
        }
        $path = $uploadsRoot . '/' . $sub;
        if (!is_file($path)) {
            printf("  [!] falta en disco: %s\n", $sub);
            $totOmitidos++;
            return [$ref, false];
        }

        $ext = strtolower(pathinfo($sub, PATHINFO_EXTENSION));
        $key = strpos($sub, '/') !== false ? $sub : $prefijo . '/' . $nombreBase . ($ext !== '' ? '.' . $ext : '');

        if ($dryRun) {
            printf("  [dry] %s -> %s\n", $sub, $key);
            $totArchivos++;
            return [urlPublica($publicBase, $key), true];
        }

        try {
            $bytes = (string) file_get_contents($path);
            $storage->guardar($key, $bytes, mimePorExtension($ext));
        } catch (Throwable $e) {
            printf("  [x] error subiendo %s: %s\n", $sub, $e->getMessage());
            $totErrores++;
            return [$ref, false];
        }

        $totArchivos++;
        return [urlPublica($publicBase, $key), true];
    };

    [$nuevaImagen, $cImg] = $migrar($imagen, 'portada');

    $nuevasCapturas = [];
    $cCap = false;
    foreach ($capturas as $idx => $ref) {
        [$nr, $ch] = $migrar((string) $ref, 'captura-' . ($idx + 1));
        $nuevasCapturas[] = $nr;
        $cCap = $cCap || (bool) $ch;
    }

    if ($cImg || $cCap) {
        $capturasJson = $nuevasCapturas === [] ? null : json_encode($nuevasCapturas);
        if (!$dryRun) {
            $upd->execute([$nuevaImagen !== '' ? $nuevaImagen : null, $capturasJson, (int) $j['id']]);
        }
        $totUpdate++;
        printf("%s id=%d %s (imagen: %s)\n", $dryRun ? '[dry]' : '[ok]', (int) $j['id'], (string) $j['titulo'], $cImg ? 'migrada' : 'sin cambio');
    }
}

printf(
    "\n%s — archivos: %d subidos/simulados, %d omitidos, %d errores; juegos actualizados: %d.\n",
    $dryRun ? 'SIMULACION' : 'MIGRACION',
    $totArchivos,
    $totOmitidos,
    $totErrores,
    $totUpdate
);

exit($totErrores > 0 ? 1 : 0);
