<?php
/**
 * scripts/worker-traducciones.php
 * Worker CLI (PHP nativo, sin broker) que consume la cola de traducciones
 * asíncronas (`traduccion_cola`) y traduce en segundo plano.
 *
 * Diseño:
 *   - Sin broker: la cola vive en PostgreSQL y se reclama con
 *     `SELECT ... FOR UPDATE SKIP LOCKED`, así que varios workers son seguros.
 *   - Reutiliza `TraduccionService::traducirEntidad()`, por lo que conserva las
 *     garantías del guardado síncrono: incremental por `origen_hash`, nunca pisa
 *     correcciones manuales (`origen='manual'`) y es fail-open.
 *   - Reintentos con backoff exponencial (2^intentos s, con tope de 300 s) y
 *     dead-letter tras `max_intentos` para los fallos permanentes.
 *
 * El modo por defecto del guardado desde el admin es 'async' (los controladores
 * encolan). Con TRANSLATE_MODE=sync se recupera el guardado síncrono y el worker
 * no recibe trabajos nuevos.
 * Mientras un job está pendiente, el contenido sigue sirviéndose en 'es'
 * (fallback), así que el sitio público nunca se rompe por un job en vuelo.
 *
 * Uso (Windows):
 *   C:\xampp\php\php.exe scripts\worker-traducciones.php [opciones]
 *
 * Opciones:
 *   --una-vez            Procesa lo que haya disponible ahora y sale.
 *   --max=N              Máximo de trabajos a procesar en total (0 = sin límite).
 *   --intervalo=N        Segundos de espera entre sondeos si la cola está vacía
 *                        (por defecto 3).
 *   --ciclos=N           Número máximo de sondeos (por defecto sin límite).
 *   --entidad=juego|categoria  Solo procesa esa entidad.
 *   --verbose            Muestra el detalle de cada trabajo.
 *   --ayuda              Muestra esta ayuda.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // Solo CLI: nunca expuesto por HTTP.
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/src/config/database.php';
require_once $root . '/src/config/TraduccionWorker.php';

// ── Argumentos ────────────────────────────────────────────────────────────
$unaVez   = false;
$max      = 0;        // 0 = sin límite
$intervalo = 3;
$ciclos   = 0;        // 0 = sin límite
$entidad  = null;     // 'juego' | 'categoria' | null
$verbose  = false;

$ayuda = static function (): void {
    echo <<<TXT
Worker de traducciones (cola en BD, PHP nativo)

Uso: php scripts/worker-traducciones.php [opciones]

  --una-vez                  Procesa lo disponible ahora y sale.
  --max=N                    Máximo de trabajos en total (0 = sin límite).
  --intervalo=N              Segundos entre sondeos si la cola está vacía (def. 3).
  --ciclos=N                 Número máximo de sondeos (def. sin límite).
  --entidad=juego|categoria  Solo procesa esa entidad.
  --verbose                  Muestra el detalle de cada trabajo.
  --ayuda                    Muestra esta ayuda.

TXT;
};

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--una-vez') {
        $unaVez = true;
    } elseif ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    } elseif ($arg === '--ayuda' || $arg === '--help' || $arg === '-h') {
        $ayuda();
        exit(0);
    } elseif (str_starts_with($arg, '--max=')) {
        $max = max(0, (int) substr($arg, 6));
    } elseif (str_starts_with($arg, '--intervalo=')) {
        $intervalo = max(1, (int) substr($arg, 12));
    } elseif (str_starts_with($arg, '--ciclos=')) {
        $ciclos = max(1, (int) substr($arg, 9));
    } elseif (str_starts_with($arg, '--entidad=')) {
        $valor = strtolower(trim(substr($arg, 10)));
        if (!in_array($valor, ['juego', 'categoria'], true)) {
            fwrite(STDERR, "Valor inválido para --entidad (usa 'juego' o 'categoria').\n");
            exit(2);
        }
        $entidad = $valor;
    } else {
        fwrite(STDERR, "Opción desconocida: {$arg}\n");
        exit(2);
    }
}

// ── Conexión y proveedor ──────────────────────────────────────────────────
$pdo = Database::tryGetInstance();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a la BD. Revisa .env (DB_*).\n");
    exit(1);
}

if (!TraduccionService::proveedorConfigurado()) {
    fwrite(STDERR, "No hay proveedor de traducción configurado.\n");
    fwrite(STDERR, "Configura TRANSLATE_PROVIDER=deepl + TRANSLATE_API_KEY en .env.\n");
    fwrite(STDERR, "Los trabajos quedan en la cola (pendientes): no se pierden.\n");
    exit(1);
}

@set_time_limit(0);

$worker = new TraduccionWorker();

echo "== Worker de traducciones (cola en BD) ==\n";
printf("Proveedor : %s\n", TraduccionService::nombreProveedor());
printf("Modo web  : %s\n", TraduccionService::modo());
printf("Destino   : %s\n", implode(', ', TraduccionService::targets()));
printf("Filtro    : %s\n", $entidad ?? 'todas las entidades');
printf("Límite    : %s trabajos / %s ciclos\n\n",
    $max > 0 ? (string) $max : 'sin límite',
    $ciclos > 0 ? (string) $ciclos : 'sin límite'
);

$procesados = 0;
$okCount    = 0;
$errCount   = 0;
$nCiclos    = 0;
$salir      = false;

while (!$salir) {
    $nCiclos++;
    $r = $worker->procesarUno($entidad);

    if ($r === null) {
        if ($unaVez || ($ciclos > 0 && $nCiclos >= $ciclos)) {
            break;
        }
        sleep($intervalo);
        continue;
    }

    if ($verbose) {
        printf(
            "[job %d] %s#%d (intento %d/%d)\n",
            $r['id'],
            $r['entidad'],
            $r['entidad_id'],
            $r['intentos'],
            $r['max_intentos']
        );
    }

    if ($r['ok']) {
        $okCount++;
        if ($verbose) {
            printf("         ok -> %s\n", implode(', ', $r['idiomas']));
        }
    } else {
        $errCount++;
        if ($r['permanente']) {
            printf(
                "[job %d] %s#%d FALLIDO (permanente: %s)\n",
                $r['id'],
                $r['entidad'],
                $r['entidad_id'],
                $r['motivo']
            );
        } else {
            $restantes = $r['max_intentos'] - $r['intentos'];
            printf(
                "[job %d] %s#%d error (%s); %s\n",
                $r['id'],
                $r['entidad'],
                $r['entidad_id'],
                $r['motivo'],
                $restantes > 0
                    ? "reintento en {$restantes} intento(s) más"
                    : 'dead-letter (sin reintentos)'
            );
        }
    }

    $procesados++;

    if ($max > 0 && $procesados >= $max) {
        $salir = true;
    }
}

printf(
    "\nResumen: %d trabajo(s) procesado(s) | %d ok | %d con error | %d ciclo(s)\n",
    $procesados,
    $okCount,
    $errCount,
    $nCiclos
);

exit($errCount > 0 ? 1 : 0);
