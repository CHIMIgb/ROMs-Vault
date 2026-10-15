<?php

require_once __DIR__ . '/TraduccionService.php';
require_once __DIR__ . '/LoggerService.php';

/**
 * config/DrenadoTrigger.php
 * Dispara, en segundo plano, el drenado de la cola de traducciones tras un
 * guardado en el admin (Opción B).
 *
 * A diferencia del drenado dentro de la misma petición (Opción A), aquí el
 * guardado responde al instante y el trabajo se ejecuta en una SEGUNDA petición
 * interna (endpoints/drain_traducciones.php) que NO se espera. Es
 * «fire-and-forget»: se abre un socket local, se escribe la petición y se cierra
 * sin leer la respuesta. Por eso no bloquea al navegador aunque el contenedor
 * sea mod_php (php:8.2-apache, sin fastcgi_finish_request).
 *
 * Fail-open total: si falta el secreto, no hay proveedor o la conexión falla, el
 * guardado sigue igual y el cron/worker retoma la cola.
 */
class DrenadoTrigger
{
    /** Ruta interna del endpoint que drena la cola. */
    private const RUTA_ENDPOINT = '/endpoints/drain_traducciones.php';

    /** Cabecera que transporta el secreto compartido del endpoint. */
    public const CABECERA = 'X-Internal-Drain';

    /** Timeout de conexión en segundos (socket local: muy corto). */
    private const TIMEOUT = 0.2;

    /**
     * ¿Hay disparo configurado y con sentido?
     *
     * Hacen falta el secreto y un proveedor de traducción configurado: sin
     * proveedor, drenar quemaría intentos de la cola sin traducir nada.
     */
    public static function disponible(): bool
    {
        return self::secreto() !== '' && TraduccionService::proveedorConfigurado();
    }

    /**
     * Dispara el drenado en segundo plano. Nunca lanza.
     *
     * @return bool true si la petición interna se pudo enviar.
     */
    public static function disparar(): bool
    {
        if (!self::disponible()) {
            return false;
        }

        try {
            $url    = self::urlInterna();
            $partes = parse_url($url);
            if (!is_array($partes) || empty($partes['host'])) {
                return false;
            }

            $host = (string) $partes['host'];
            $port = (int) ($partes['port'] ?? 80);
            if ($port <= 0) {
                $port = 80;
            }

            $sock = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT);
            if (!is_resource($sock)) {
                return false;
            }

            @fwrite($sock, self::construirPeticion(self::secreto(), $url));
            @fclose($sock);

            return true;
        } catch (Throwable $e) {
            LoggerService::write('traduccion_drenado_trigger_error', [
                'error' => $e->getMessage(),
            ], 'warning');

            return false;
        }
    }

    /**
     * Petición HTTP cruda que se envía al endpoint. Función pura (testeable).
     */
    public static function construirPeticion(string $secreto, string $url): string
    {
        $partes = parse_url($url);
        $host   = is_array($partes) && !empty($partes['host']) ? (string) $partes['host'] : '127.0.0.1';
        $port   = is_array($partes) ? (int) ($partes['port'] ?? 80) : 80;
        if ($port <= 0) {
            $port = 80;
        }
        $ruta = is_array($partes) && !empty($partes['path']) ? (string) $partes['path'] : self::RUTA_ENDPOINT;

        return "GET {$ruta} HTTP/1.1\r\n"
            . "Host: {$host}:{$port}\r\n"
            . self::CABECERA . ": {$secreto}\r\n"
            . "Connection: close\r\n"
            . "\r\n";
    }

    /**
     * URL interna del endpoint: INTERNAL_DRAIN_URL si está definida; si no, se
     * deriva del puerto local (SERVER_PORT, o PORT como respaldo).
     */
    public static function urlInterna(): string
    {
        $override = self::env('INTERNAL_DRAIN_URL');
        if ($override !== '') {
            return $override;
        }

        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        if ($port <= 0) {
            $port = (int) (self::env('PORT') ?: 0);
        }
        if ($port <= 0) {
            $port = 80;
        }

        return 'http://127.0.0.1:' . $port . self::RUTA_ENDPOINT;
    }

    /** Secreto compartido con el endpoint (INTERNAL_DRAIN_SECRET). */
    private static function secreto(): string
    {
        return self::env('INTERNAL_DRAIN_SECRET');
    }

    /** Lee una variable de entorno priorizando $_ENV (ver database.php). */
    private static function env(string $clave): string
    {
        $valor = $_ENV[$clave] ?? getenv($clave);

        return is_string($valor) ? trim($valor) : '';
    }
}
