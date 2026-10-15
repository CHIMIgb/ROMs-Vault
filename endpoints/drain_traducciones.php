<?php

/**
 * drain_traducciones.php
 * Endpoint interno — drena la cola de traducciones (`traduccion_cola`).
 *
 * NO es público: exige el secreto compartido INTERNAL_DRAIN_SECRET en la
 * cabecera X-Internal-Drain (comparado en tiempo constante). Lo invoca
 * DrenadoTrigger tras un guardado en el admin, en una petición aparte que no se
 * espera (fire-and-forget), de modo que el formulario responde al instante.
 *
 * Se ejecuta como entrypoint autónomo (fuera de index.php): no pasa por la
 * sesión ni por el CSRF global, de ahí que tenga su propia autenticación.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../src/config/TraduccionWorker.php';

// Solo tiene sentido por HTTP (lo llama el propio servidor, no un cron).
if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Este endpoint solo se ejecuta desde el servidor web.\n");
    exit(1);
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$secreto  = $_ENV['INTERNAL_DRAIN_SECRET'] ?? getenv('INTERNAL_DRAIN_SECRET');
$recibido = $_SERVER['HTTP_X_INTERNAL_DRAIN'] ?? '';

$autorizado = is_string($secreto) && $secreto !== ''
    && is_string($recibido) && hash_equals($secreto, $recibido);

if (!$autorizado) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// El cliente cierra el socket en cuanto envía: el drenado debe continuar igual.
ignore_user_abort(true);
@set_time_limit(0);

try {
    $resumen = TraduccionWorker::drenarOportunista();
    http_response_code(200);
    echo json_encode($resumen);
} catch (Throwable $e) {
    LoggerService::write('traduccion_drain_endpoint_error', [
        'error' => $e->getMessage(),
    ], 'warning');

    http_response_code(500);
    echo json_encode(['procesados' => 0, 'ok' => 0, 'error' => 0]);
}
exit;
