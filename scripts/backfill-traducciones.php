<?php
/**
 * scripts/backfill-traducciones.php
 * Traduce de una pasada el contenido editorial existente y puebla las tablas
 * de traducciones (i18n, bloque 2 / Fase 3).
 *
 *   - categorias_traducciones : categorias.nombre + categorias.descripcion
 *   - juegos_traducciones     : juegos.descripcion + juegos.idiomas
 *
 * Requiere un proveedor configurado en .env para el modo REAL:
 *   TRANSLATE_PROVIDER=deepl + TRANSLATE_API_KEY=<clave DeepL>
 * (o TRANSLATE_PROVIDER=google + TRANSLATE_API_KEY=...).
 * El modo --dry-run NO necesita proveedor: solo previsualiza lo pendiente.
 *
 * Idempotente e incremental: solo traduce lo que falta o cambió en 'es'
 * (detección por origen_hash) y NUNCA pisa correcciones manuales
 * (origen = 'manual'). Ejecutarlo dos veces no duplica ni repite trabajo.
 *
 * Los nombres propios NO se traducen (juegos.titulo, consolas.*, emuladores.*).
 *
 * Uso (Windows):
 *   C:\xampp\php\php.exe scripts\backfill-traducciones.php [opciones]
 *
 * Opciones:
 *   --dry-run            Previsualiza lo pendiente sin llamar al proveedor.
 *   --solo=categorias    Solo categorías (o --solo=juegos).
 *   --campo=idiomas      Solo el campo `idiomas` de los juegos (no re-traduce la
 *                        descripción ya guardada). Fuerza --solo=juegos. No
 *                        necesita proveedor: usa la tabla determinista.
 *   --forzar             Re-genera aunque el origen_hash ya coincida (para
 *                        corregir filas ya escritas). Respeta origen='manual'.
 *   --idioma=en,fr,ja    Limita los idiomas destino (coma-separados).
 *   --limite=50          Máximo de entidades por tipo.
 *   --ayuda              Muestra esta ayuda.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // Solo CLI: nunca expuesto por HTTP.
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/config/TraduccionService.php';
require_once $root . '/src/config/i18n.php';

// ── Argumentos ────────────────────────────────────────────────────────────
$dryRun  = false;
$forzar  = false;         // re-generar aunque el hash coincida
$solo    = null;          // 'categorias' | 'juegos' | null (ambos)
$campo   = null;          // null (todos) | 'idiomas' (solo el campo idiomas de juegos)
$idiomas = null;          // array<int,string> | null
$limite  = null;          // int | null

$ayuda = static function (): void {
    echo <<<TXT
Backfill de traducciones de contenido (i18n, bloque 2)

Uso: php scripts/backfill-traducciones.php [opciones]

  --dry-run            Previsualiza lo pendiente sin llamar al proveedor.
  --solo=categorias    Solo categorías (o --solo=juegos).
  --campo=idiomas      Solo el campo idiomas de los juegos (no re-traduce la descripción).
  --forzar             Re-genera aunque el hash ya coincida (para corregir filas escritas).
  --idioma=en,fr,ja    Limita los idiomas destino (coma-separados).
  --limite=50          Máximo de entidades por tipo.
  --ayuda              Muestra esta ayuda.

TXT;
};

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--forzar') {
        $forzar = true;
    } elseif ($arg === '--ayuda' || $arg === '--help' || $arg === '-h') {
        $ayuda();
        exit(0);
    } elseif (str_starts_with($arg, '--solo=')) {
        $valor = strtolower(trim(substr($arg, 7)));
        if (!in_array($valor, ['categorias', 'juegos'], true)) {
            fwrite(STDERR, "Valor inválido para --solo (usa 'categorias' o 'juegos').\n");
            exit(2);
        }
        $solo = $valor;
    } elseif (str_starts_with($arg, '--campo=')) {
        $valor = strtolower(trim(substr($arg, 8)));
        if ($valor !== 'idiomas') {
            fwrite(STDERR, "Valor inválido para --campo (solo se admite 'idiomas').\n");
            exit(2);
        }
        $campo = $valor;
    } elseif (str_starts_with($arg, '--idioma=')) {
        $idiomas = array_values(array_filter(
            array_map('trim', explode(',', substr($arg, 9))),
            static fn(string $v): bool => $v !== ''
        ));
        if ($idiomas === []) {
            fwrite(STDERR, "Valor inválido para --idioma (lista coma-separada).\n");
            exit(2);
        }
    } elseif (str_starts_with($arg, '--limite=')) {
        $limite = max(0, (int) substr($arg, 9));
    } else {
        fwrite(STDERR, "Opción desconocida: {$arg}\n");
        exit(2);
    }
}

// --campo=idiomas solo aplica a juegos (las categorías no tienen ese campo).
if ($campo === 'idiomas') {
    if ($solo === 'categorias') {
        fwrite(STDERR, "--campo=idiomas no es compatible con --solo=categorias.\n");
        exit(2);
    }
    $solo = 'juegos';
}

// ── Conexión y configuración ──────────────────────────────────────────────
$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

$destino = TraduccionService::targets($idiomas);
if ($destino === []) {
    fwrite(STDERR, "No hay idiomas destino válidos. Revisa --idioma o TRANSLATE_TARGETS.\n");
    exit(1);
}

// El campo `idiomas` se resuelve con una tabla determinista (sin red), así que
// en ese modo no se exige proveedor configurado.
$usaProveedor = $campo !== 'idiomas';
if (!$dryRun && $usaProveedor && !TraduccionService::proveedorConfigurado()) {
    fwrite(STDERR, "No hay proveedor de traducción configurado.\n");
    fwrite(STDERR, "Configura TRANSLATE_PROVIDER=deepl + TRANSLATE_API_KEY en .env,\n");
    fwrite(STDERR, "o previsualiza primero con --dry-run.\n");
    exit(1);
}

echo "== Backfill de traducciones (i18n, bloque 2) ==\n";
printf("Proveedor : %s\n", $dryRun ? 'n/a (dry-run)' : ($usaProveedor ? TraduccionService::nombreProveedor() : 'n/a (tabla idiomas)'));
printf("Destino   : %s (%d)\n", implode(', ', $destino), count($destino));
printf("Modo      : %s%s\n\n", $dryRun ? 'DRY-RUN (sin escribir)' : 'REAL', $forzar ? ' + FORZAR' : '');

// ── Carga del catálogo ────────────────────────────────────────────────────
$limitSql = $limite !== null ? ' LIMIT ' . (int) $limite : '';

try {
    $categorias = ($solo === null || $solo === 'categorias')
        ? $pdo->query("SELECT id, nombre, descripcion FROM categorias ORDER BY id{$limitSql}")->fetchAll()
        : [];

    $juegos = ($solo === null || $solo === 'juegos')
        ? $pdo->query(
            "SELECT id, descripcion, idiomas FROM juegos
             WHERE (descripcion IS NOT NULL AND btrim(descripcion) <> '')
                OR (idiomas      IS NOT NULL AND btrim(idiomas)      <> '')
             ORDER BY id{$limitSql}"
          )->fetchAll()
        : [];
} catch (PDOException $e) {
    fwrite(STDERR, "Error consultando el catálogo. ¿Aplicaste la migración de traducciones?\n");
    fwrite(STDERR, "  data/migrations/2026-10-12-traducciones-contenido.sql\n");
    fwrite(STDERR, "Detalle: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Procesamiento ─────────────────────────────────────────────────────────
/**
 * @param array<int,array>       $filas
 * @param callable(array):array  $campos
 * @param callable(string,int,array):array $traducir
 * @return array{con_pendientes:int,escritas:int,errores:int,con_error:int}
 */
$procesar = static function (
    string $entidad,
    array $filas,
    callable $campos,
    callable $traducir,
    array $destino,
    bool $dryRun
): array {
    $conPendientes = 0;
    $escritas      = 0;
    $errores       = 0;
    $conError      = 0;

    foreach ($filas as $fila) {
        $id  = (int) $fila['id'];
        $res = $traducir($entidad, $id, $campos($fila));

        if (!($res['ok'] ?? false)) {
            continue; // p. ej. sin_texto
        }

        $idiomas = $dryRun ? ($res['pendientes'] ?? []) : ($res['idiomas'] ?? []);
        $err     = (int) ($res['errores'] ?? 0);
        $errores += $err;

        if ($dryRun) {
            if ($idiomas === []) {
                continue;
            }
            $conPendientes++;
            printf("  %-6s #%-5d -> %d pendiente(s): %s\n", $entidad, $id, count($idiomas), implode(' ', $idiomas));
        } else {
            $escritas += count($idiomas);
            if ($err > 0) {
                $conError++;
            }
            if ($idiomas !== [] || $err > 0) {
                printf("  %-6s #%-5d -> %d OK%s\n", $entidad, $id, count($idiomas), $err > 0 ? ", {$err} error(es)" : '');
            }
        }
    }

    return [
        'con_pendientes' => $conPendientes,
        'escritas'       => $escritas,
        'errores'        => $errores,
        'con_error'      => $conError,
    ];
};

// Traductores: por defecto, todos los campos; con --campo=idiomas, solo idiomas.
$traducirTodo = static fn(string $entidad, int $id, array $campos): array
    => TraduccionService::traducirEntidad($entidad, $id, $campos, $destino, $dryRun, $forzar);
$traducirIdiomas = static fn(string $entidad, int $id, array $campos): array
    => TraduccionService::traducirIdiomasJuego($id, $campos, $destino, $dryRun, $forzar);

$rc = $procesar('categoria', $categorias, static fn(array $f): array => [
    'nombre'      => (string) ($f['nombre'] ?? ''),
    'descripcion' => (string) ($f['descripcion'] ?? ''),
], $traducirTodo, $destino, $dryRun);

$rj = $procesar('juego', $juegos, static fn(array $f): array => [
    'descripcion' => (string) ($f['descripcion'] ?? ''),
    'idiomas'     => (string) ($f['idiomas'] ?? ''),
], $campo === 'idiomas' ? $traducirIdiomas : $traducirTodo, $destino, $dryRun);

// ── Resumen ───────────────────────────────────────────────────────────────
echo "\n--- Resumen ---\n";
if ($dryRun) {
    printf("Categorías : %d con pendientes\n", $rc['con_pendientes']);
    printf("Juegos     : %d con pendientes\n", $rj['con_pendientes']);
    printf("Total      : %d traducción(es) pendiente(s). Ejecuta sin --dry-run para generarlas.\n",
        $rc['con_pendientes'] * count($destino) + $rj['con_pendientes'] * count($destino));
    exit(0);
}

printf("Categorías : %d traducción(es) escritas\n", $rc['escritas']);
printf("Juegos     : %d traducción(es) escritas\n", $rj['escritas']);
printf("Errores    : %d (entidades: %d)\n", $rc['errores'] + $rj['errores'], $rc['con_error'] + $rj['con_error']);
printf("Total      : %d traducción(es) escritas.\n", $rc['escritas'] + $rj['escritas']);

exit(($rc['errores'] + $rj['errores']) > 0 ? 1 : 0);
