<?php

require_once __DIR__ . '/TranslationProvider.php';

/**
 * config/translation/AbstractTranslateProvider.php
 * Base común de los proveedores HTTP (DeepL, Google).
 *
 * Centraliza el POST JSON con cURL, el manejo de textos vacíos y la
 * reasignación de resultados por posición. Cada proveedor solo implementa
 * request() y, si hace falta, el mapeo de códigos de idioma.
 *
 * El llamante (TraduccionService) captura las excepciones: un fallo de red o
 * del servicio nunca debe romper el guardado del contenido.
 */

abstract class AbstractTranslateProvider implements TranslationProvider {
    /** Tiempo máximo de espera de cada llamada, en segundos. */
    protected int $timeout;

    public function __construct(int $timeout = 8) {
        $this->timeout = $timeout > 0 ? $timeout : 8;
    }

    public function translateBatch(array $textos, string $source, string $target): array {
        // Solo se envía lo que tiene contenido; los vacíos se devuelven tal cual.
        $enviar = [];
        foreach ($textos as $clave => $texto) {
            if (trim($texto) !== '') {
                $enviar[$clave] = $texto;
            }
        }
        if ($enviar === []) {
            return $textos;
        }

        $traducidos = $this->request(
            array_values($enviar),
            $this->mapCode($source),
            $this->mapCode($target)
        );

        // Reasignar por posición: las APIs devuelven en el mismo orden enviado.
        $salida = $textos;
        $i = 0;
        foreach (array_keys($enviar) as $clave) {
            if (isset($traducidos[$i]) && $traducidos[$i] !== '') {
                $salida[$clave] = $traducidos[$i];
            }
            $i++;
        }
        return $salida;
    }

    /**
     * Envía los textos al proveedor y devuelve las traducciones en orden.
     *
     * @param array<int,string> $textos
     * @return array<int,string>
     * @throws RuntimeException Si el proveedor no responde o responde mal.
     */
    abstract protected function request(array $textos, string $source, string $target): array;

    /** Mapeo del código de la app al código del proveedor (BCP-47 si aplica). */
    protected function mapCode(string $codigo): string {
        return $codigo;
    }

    /**
     * Cabeceras HTTP adicionales del proveedor (p. ej. Authorization).
     * El Content-Type JSON lo añade siempre postJson(); aquí solo van las
     * extra. Por defecto no hay ninguna.
     *
     * @return string[]
     */
    protected function extraHeaders(): array {
        return [];
    }

    /**
     * POST JSON con cURL. Lanza RuntimeException si no hay respuesta válida.
     */
    protected function postJson(string $url, array $payload): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extensión cURL de PHP no está disponible.');
        }

        $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($cuerpo === false) {
            throw new RuntimeException('No se pudo serializar la petición de traducción.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json'],
                $this->extraHeaders()
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
        ]);

        $respuesta = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException('Error de red al traducir: ' . $error);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('El proveedor de traducción respondió HTTP ' . $httpCode);
        }

        $datos = json_decode((string) $respuesta, true);
        if (!is_array($datos)) {
            throw new RuntimeException('Respuesta de traducción no válida (JSON).');
        }
        return $datos;
    }
}
