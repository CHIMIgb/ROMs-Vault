<?php

require_once __DIR__ . '/TraduccionService.php';
require_once __DIR__ . '/LoggerService.php';

/**
 * config/TraduccionWorker.php
 * Núcleo de procesamiento de la cola de traducciones (`traduccion_cola`).
 *
 * Extrae la lógica que antes vivía solo en el worker CLI
 * (scripts/worker-traducciones.php) para poder reutilizarla en dos sitios:
 *
 *   1. El worker CLI (proceso persistente / cron).
 *   2. El «drenado oportunista» tras guardar en el admin (Opción A): se
 *      responde al navegador y, ya sin conexión abierta, se procesan unos
 *      pocos trabajos. Así las traducciones aparecen en segundos sin depender
 *      de que el cron se dispare.
 *
 * Reutiliza `TraduccionService::traducirEntidad()`, por lo que conserva sus
 * garantías: incremental por `origen_hash`, nunca pisa correcciones manuales
 * (`origen='manual'`) y es fail-open.
 */
class TraduccionWorker
{
    /** Motivos que no mejoran con reintentos (van directos a dead-letter). */
    public const MOTIVOS_PERMANENTES = ['entidad_invalida', 'sin_texto', 'sin_idiomas'];

    private TraduccionCola $cola;

    /**
     * @param TraduccionCola|null $cola Cola a usar (inyectable para tests).
     */
    public function __construct(?TraduccionCola $cola = null)
    {
        $this->cola = $cola ?? new TraduccionCola();
    }

    /**
     * Reclama y procesa un único trabajo de la cola.
     *
     * @param string|null $entidad Filtra por 'juego' o 'categoria' (null = cualquiera).
     * @return array|null Resumen del trabajo, o null si no había nada pendiente.
     *                    Claves: id, entidad, entidad_id, intentos, max_intentos,
     *                    ok, errores, motivo, permanente, idiomas.
     */
    public function procesarUno(?string $entidad = null): ?array
    {
        $job = $this->cola->reclamar($entidad);
        if ($job === null) {
            return null;
        }

        $id     = (int) $job['id'];
        $ent    = (string) $job['entidad'];
        $entId  = (int) $job['entidad_id'];
        $intent = (int) $job['intentos'];
        $maxInt = (int) $job['max_intentos'];

        try {
            $res = TraduccionService::traducirEntidad($ent, $entId, (array) $job['campos']);
        } catch (Throwable $e) {
            // Fail-open: el job vuelve a la cola con backoff en vez de romper
            // el worker (o la petición que lo drena).
            $res = ['ok' => false, 'motivo' => $e->getMessage(), 'idiomas' => []];
        }

        $ok     = !empty($res['ok']);
        $err    = (int) ($res['errores'] ?? 0);
        $motivo = null;
        $perm   = false;

        if ($ok && $err === 0) {
            $this->cola->marcarOk($id);
        } else {
            $motivo = $ok ? ($err . ' idioma(s) con error') : (string) ($res['motivo'] ?? 'error');
            if (!$ok && in_array($motivo, self::MOTIVOS_PERMANENTES, true)) {
                $this->cola->marcarFallido($id, $motivo);
                $perm = true;
            } else {
                $this->cola->marcarError($id, $intent, $maxInt, $motivo);
            }
        }

        return [
            'id'           => $id,
            'entidad'      => $ent,
            'entidad_id'   => $entId,
            'intentos'     => $intent,
            'max_intentos' => $maxInt,
            'ok'           => $ok && $err === 0,
            'errores'      => $err,
            'motivo'       => $motivo,
            'permanente'   => $perm,
            'idiomas'      => (array) $res['idiomas'],
        ];
    }

    /**
     * Drena hasta $max trabajos dentro de un presupuesto de tiempo.
     *
     * No espera si la cola está vacía (devuelve enseguida). Es el bloque base
     * del drenado oportunista del admin y también sirve para pruebas.
     *
     * Nota: el presupuesto acota cuándo EMPEZAR un trabajo nuevo, no interrumpe
     * el que ya está en curso (una entidad con muchos idiomas puede tardar más).
     *
     * @param int         $max                 Máximo de trabajos (0 = sin límite).
     * @param float       $presupuestoSegundos Tope de tiempo en segundos (0 = sin límite).
     * @param string|null $entidad             Filtra por entidad (null = cualquiera).
     * @return array{procesados:int,ok:int,error:int}
     */
    public function drenar(int $max = 0, float $presupuestoSegundos = 0.0, ?string $entidad = null): array
    {
        $inicio  = microtime(true);
        $resumen = ['procesados' => 0, 'ok' => 0, 'error' => 0];

        while (true) {
            if ($max > 0 && $resumen['procesados'] >= $max) {
                break;
            }
            if ($presupuestoSegundos > 0.0 && (microtime(true) - $inicio) >= $presupuestoSegundos) {
                break;
            }

            $r = $this->procesarUno($entidad);
            if ($r === null) {
                break;
            }

            $resumen['procesados']++;
            if ($r['ok']) {
                $resumen['ok']++;
            } else {
                $resumen['error']++;
            }
        }

        return $resumen;
    }

    /**
     * Drenado oportunista tras un guardado en el admin (Opción A).
     *
     * Best-effort y muy acotado. Solo actúa si hay proveedor configurado: sin
     * él, `traducirEntidad()` devolvería error y quemaría intentos de la cola
     * (nunca tocamos la cola en ese caso).
     *
     * El tamaño se puede ajustar con TRANSLATE_OPORTUNISTA_MAX (trabajos) y
     * TRANSLATE_OPORTUNISTA_PRESUPUESTO (segundos).
     *
     * @return array{procesados:int,ok:int,error:int}
     */
    public static function drenarOportunista(?int $max = null, ?float $presupuesto = null): array
    {
        $vacio = ['procesados' => 0, 'ok' => 0, 'error' => 0];

        if (!TraduccionService::proveedorConfigurado()) {
            return $vacio;
        }

        $max ??= (int) (getenv('TRANSLATE_OPORTUNISTA_MAX') ?: 3);
        $presupuesto ??= (float) (getenv('TRANSLATE_OPORTUNISTA_PRESUPUESTO') ?: 5.0);

        try {
            return (new self())->drenar($max > 0 ? $max : 0, $presupuesto > 0 ? $presupuesto : 0.0);
        } catch (Throwable $e) {
            LoggerService::write('traduccion_worker_oportunista_error', [
                'error' => $e->getMessage(),
            ], 'warning');
            return $vacio;
        }
    }
}
