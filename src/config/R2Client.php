<?php

/**
 * src/config/R2Client.php
 * Cliente minimo compatible con S3 (firma AWS SigV4) para Cloudflare R2.
 *
 * Cloudflare R2 expone una API compatible con S3: el mismo protocolo de firma
 * SigV4 con region 'auto' y endpoint propio. NO es Amazon S3: solo se reutiliza
 * el protocolo para no anadir dependencias (no se usa aws/aws-sdk-php).
 *
 * Operaciones necesarias por el proyecto (portadas/capturas):
 *   - putObject()    subir un objeto (bytes + content-type).
 *   - deleteObject() borrar un objeto.
 *   - copyObject()   copiar un objeto (mover al renombrar).
 *   - headObject()   comprobar existencia (sustituye file_exists).
 *   - listObjects()  listar claves por prefijo (sustituye is_dir/scandir).
 *
 * Sin dependencias externas: solo cURL y las funciones hash/JSON de PHP.
 */
class R2Client
{
    private string $endpoint;
    private string $host;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;
    private string $region;

    private int $lastStatus = 0;
    private string $lastBody = '';

    /**
     * @param string $endpoint  https://<account>.r2.cloudflarestorage.com
     * @param string $bucket    Nombre del bucket.
     * @param string $region    Region SigV4 de R2 (siempre 'auto').
     */
    public function __construct(
        string $endpoint,
        string $bucket,
        string $accessKey,
        string $secretKey,
        string $region = 'auto'
    ) {
        $this->endpoint  = rtrim($endpoint, '/');
        $this->host      = (string) (parse_url($this->endpoint, PHP_URL_HOST) ?? '');
        $this->bucket    = $bucket;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region    = $region !== '' ? $region : 'auto';
    }

    public function lastStatus(): int
    {
        return $this->lastStatus;
    }

    public function lastBody(): string
    {
        return $this->lastBody;
    }

    /** Sube (o reemplaza) un objeto. @param string $body bytes binarios del archivo. */
    public function putObject(string $key, string $body, string $contentType): bool
    {
        $res = $this->request('PUT', $key, $body, ['content-type' => $contentType]);
        return $this->isSuccess($res['status']);
    }

    public function deleteObject(string $key): bool
    {
        // S3/R2 responde 204 aunque el objeto no exista (borrado idempotente).
        $res = $this->request('DELETE', $key);
        return $this->isSuccess($res['status']);
    }

    public function copyObject(string $sourceKey, string $destKey): bool
    {
        $res = $this->request('PUT', $destKey, '', [
            'x-amz-copy-source' => '/' . $this->bucket . '/' . $this->encodeKey($sourceKey),
        ]);
        return $this->isSuccess($res['status']);
    }

    public function headObject(string $key): bool
    {
        $res = $this->request('HEAD', $key);
        return $res['status'] === 200;
    }

    /**
     * Lista las claves bajo un prefijo (paginado con continuation-token).
     *
     * @return string[]
     */
    public function listObjects(string $prefix): array
    {
        $keys  = [];
        $token = null;

        do {
            $query = ['list-type' => '2', 'prefix' => $prefix];
            if ($token !== null) {
                $query['continuation-token'] = $token;
            }
            $res = $this->request('GET', '', null, [], $query);
            if (!$this->isSuccess($res['status'])) {
                break;
            }
            $xml = @simplexml_load_string($res['body']);
            if ($xml === false) {
                break;
            }
            foreach ($xml->Contents as $contenido) {
                $keys[] = (string) $contenido->Key;
            }
            $next  = (string) ($xml->NextContinuationToken ?? '');
            $token = $next !== '' ? $next : null;
        } while ($token !== null);

        return $keys;
    }

    /**
     * Ejecuta una peticion firmada con SigV4.
     *
     * @return array{status:int, body:string}
     */
    private function request(
        string $method,
        string $key,
        ?string $body = null,
        array $headers = [],
        array $query = []
    ): array {
        $path        = $this->buildPath($key);
        $queryString = $this->buildQueryString($query);
        $payloadHash = hash('sha256', $body ?? '');
        $amzDate     = gmdate('Ymd\THis\Z');
        $dateStamp   = substr($amzDate, 0, 8);

        // Cabeceras firmadas (obligatorias + las del metodo).
        $signedHeaders = [
            'host'                 => $this->host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
        ];
        foreach ($headers as $nombre => $valor) {
            $signedHeaders[strtolower($nombre)] = (string) $valor;
        }

        $authorization = $this->authorization(
            $method,
            $path,
            $queryString,
            $signedHeaders,
            $payloadHash,
            $amzDate,
            $dateStamp
        );

        $httpHeaders = [];
        foreach ($signedHeaders as $nombre => $valor) {
            $httpHeaders[] = $nombre . ': ' . $this->normalizeHeaderValue($valor);
        }
        $httpHeaders[] = 'authorization: ' . $authorization;
        // Evita la espera de "Expect: 100-continue" en cuerpos pequenos.
        $httpHeaders[] = 'Expect:';

        $url = $this->endpoint . $path . ($queryString !== '' ? '?' . $queryString : '');

        $ch = curl_init($url);
        $opciones = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($method === 'HEAD') {
            $opciones[CURLOPT_NOBODY] = true;
        }
        if ($body !== null) {
            $opciones[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opciones);

        $respuesta = curl_exec($ch);
        if ($respuesta === false) {
            $this->lastStatus = 0;
            $this->lastBody   = (string) curl_error($ch);
            curl_close($ch);
            return ['status' => 0, 'body' => $this->lastBody];
        }

        $this->lastStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastBody   = (string) $respuesta;
        curl_close($ch);

        return ['status' => $this->lastStatus, 'body' => $this->lastBody];
    }

    /**
     * Construye la cabecera Authorization (AWS4-HMAC-SHA256).
     *
     * @param array<string,string> $signedHeaders
     */
    private function authorization(
        string $method,
        string $path,
        string $queryString,
        array $signedHeaders,
        string $payloadHash,
        string $amzDate,
        string $dateStamp
    ): string {
        ksort($signedHeaders);

        $canonicalHeaders = '';
        $signedList       = [];
        foreach ($signedHeaders as $nombre => $valor) {
            $nombreClave = strtolower($nombre);
            $canonicalHeaders .= $nombreClave . ':' . $this->normalizeHeaderValue($valor) . "\n";
            $signedList[] = $nombreClave;
        }
        $signedHeadersStr = implode(';', $signedList);

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            $queryString,
            $canonicalHeaders,
            $signedHeadersStr,
            $payloadHash,
        ]);

        $scope        = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $firma    = hash_hmac('sha256', $stringToSign, $kSigning);

        return 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeadersStr
            . ', Signature=' . $firma;
    }

    /** Ruta canonica: /{bucket} o /{bucket}/{key-codificada}. */
    private function buildPath(string $key): string
    {
        if ($key === '') {
            return '/' . $this->bucket;
        }
        return '/' . $this->bucket . '/' . $this->encodeKey($key);
    }

    private function buildQueryString(array $query): string
    {
        if ($query === []) {
            return '';
        }
        ksort($query);
        $partes = [];
        foreach ($query as $nombre => $valor) {
            $partes[] = rawurlencode((string) $nombre) . '=' . rawurlencode((string) $valor);
        }
        return implode('&', $partes);
    }

    /** Codifica cada segmento de la clave (conserva las barras). */
    private function encodeKey(string $key): string
    {
        $segmentos = explode('/', $key);
        return implode('/', array_map('rawurlencode', $segmentos));
    }

    private function normalizeHeaderValue(string $valor): string
    {
        return trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
    }

    private function isSuccess(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }
}
