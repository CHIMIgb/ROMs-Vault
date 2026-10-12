<?php

/**
 * src/config/StorageService.php
 * Capa de almacenamiento de imagenes (portadas/capturas).
 *
 * Abstrae "donde" se guardan los archivos para que AdminController siga con la
 * MISMA estructura y logica, cambiando solo el destino:
 *   - modo R2    -> objetos en Cloudflare R2 (clave {consola}/{juego}/archivo.webp),
 *                   la BD guarda la URL publica completa.
 *   - modo local -> public/uploads/{consola}/{juego}/archivo.webp (fallback para
 *                   desarrollo y tests; se activa cuando faltan credenciales R2).
 *
 * La clave de un objeto es la ruta relativa bajo uploads, p. ej.
 *   psp/metal-gear-solid-peace-walker/portada.webp
 *
 * Sin dependencias externas.
 */
class StorageService
{
    /** Prefijo de las rutas locales (relativo a la raiz del proyecto). */
    public const LOCAL_PREFIX = 'public/uploads';

    private string $mode = 'local';
    private ?R2Client $client = null;
    private string $publicBase = '';
    private string $publicHost = '';
    private string $localRoot;

    public function __construct()
    {
        $this->localRoot  = __DIR__ . '/../../' . self::LOCAL_PREFIX;
        $this->publicBase = rtrim($this->env('R2_PUBLIC_BASE'), '/');
        $this->publicHost = $this->env('R2_PUBLIC_HOST');

        $bucket    = $this->env('R2_BUCKET');
        $endpoint  = $this->env('R2_ENDPOINT');
        $accessKey = $this->env('R2_ACCESS_KEY_ID');
        $secretKey = $this->env('R2_ACCESS_KEY_SECRET');

        if ($endpoint !== '' && $bucket !== '' && $accessKey !== '' && $secretKey !== '' && $this->publicBase !== '') {
            // Carga perezosa de R2Client dentro del constructor: evita efectos
            // colaterales en el scope global del archivo (cumple PSR-1/PSR-12).
            require_once __DIR__ . '/R2Client.php';
            $region       = $this->env('R2_REGION') !== '' ? $this->env('R2_REGION') : 'auto';
            $this->client = new R2Client($endpoint, $bucket, $accessKey, $secretKey, $region);
            $this->mode   = 'r2';
        }
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * URL del icono de la aplicacion para metadatos SEO (JSON-LD / Open Graph).
     * El favicon y el logo del header se sirven siempre desde el asset estatico
     * public/img/icon.png; aqui solo se resuelve la URL absoluta del fallback
     * cuando hay imagen: en R2 apunta a la copia publica del bucket.
     */
    public static function iconoUrl(): string
    {
        $s = new self();
        if ($s->isRemote()) {
            return $s->urlPublica('icon.png');
        }
        return 'public/img/icon.png';
    }

    private function env(string $name): string
    {
        $valor = $_ENV[$name] ?? getenv($name);
        return is_string($valor) ? trim($valor) : '';
    }

    public function isRemote(): bool
    {
        return $this->mode === 'r2';
    }

    public function publicHost(): string
    {
        return $this->publicHost;
    }

    public function client(): ?R2Client
    {
        return $this->client;
    }

    /**
     * Guarda bytes en la clave indicada y devuelve la referencia para la BD:
     * URL publica completa (R2) o ruta local relativa (fallback).
     *
     * @throws RuntimeException si la subida a R2 falla.
     */
    public function guardar(string $key, string $bytes, string $contentType = 'image/webp'): string
    {
        $key = $this->normalizarKey($key);
        if ($key === '') {
            throw new RuntimeException('Clave de almacenamiento no valida');
        }

        if ($this->isRemote()) {
            if (!$this->client->putObject($key, $bytes, $contentType)) {
                throw new RuntimeException('R2 putObject fallo (HTTP ' . $this->client->lastStatus() . ')');
            }
            return $this->urlPublica($key);
        }

        $abs = $this->localRoot . '/' . $key;
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (file_put_contents($abs, $bytes) === false) {
            throw new RuntimeException('No se pudo escribir la imagen en disco');
        }
        return self::LOCAL_PREFIX . '/' . $key;
    }

    /** Borra un objeto/archivo (idempotente). */
    public function borrar(string $key): void
    {
        $key = $this->normalizarKey($key);
        if ($key === '') {
            return;
        }
        if ($this->isRemote()) {
            $this->client->deleteObject($key);
            return;
        }
        $abs = $this->localRoot . '/' . $key;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /** Indica si existe el objeto/archivo. */
    public function existe(string $key): bool
    {
        $key = $this->normalizarKey($key);
        if ($key === '') {
            return false;
        }
        if ($this->isRemote()) {
            return $this->client->headObject($key);
        }
        return is_file($this->localRoot . '/' . $key);
    }

    /**
     * Indica si hay algun objeto/archivo bajo el prefijo dado.
     * Sustituye a is_dir() para la deduplicacion de carpetas de juego.
     */
    public function prefijoOcupado(string $prefix): bool
    {
        $prefix = trim($this->normalizarKey($prefix), '/');
        if ($prefix === '') {
            return false;
        }
        if ($this->isRemote()) {
            return $this->client->listObjects($prefix . '/') !== [];
        }
        return is_dir($this->localRoot . '/' . $prefix);
    }

    /** Borra todos los objetos/archivos bajo un prefijo (carpeta del juego). */
    public function borrarPrefijo(string $prefix): void
    {
        $prefix = trim($this->normalizarKey($prefix), '/');
        if ($prefix === '') {
            return;
        }
        if ($this->isRemote()) {
            foreach ($this->client->listObjects($prefix . '/') as $key) {
                $this->client->deleteObject($key);
            }
            return;
        }
        $this->rmdirRecursive($this->localRoot . '/' . $prefix);
    }

    /** Copia todos los objetos/archivos de un prefijo a otro (mover). */
    public function copiarPrefijo(string $origenPrefix, string $destPrefix): void
    {
        $origen = trim($this->normalizarKey($origenPrefix), '/');
        $dest   = trim($this->normalizarKey($destPrefix), '/');
        if ($origen === '' || $dest === '') {
            return;
        }
        if ($this->isRemote()) {
            foreach ($this->client->listObjects($origen . '/') as $key) {
                $resto = substr($key, strlen($origen) + 1);
                $this->client->copyObject($key, $dest . '/' . $resto);
            }
            return;
        }
        $this->moverLocal($this->localRoot . '/' . $origen, $this->localRoot . '/' . $dest);
    }

    public function urlPublica(string $key): string
    {
        $key = ltrim($this->normalizarKey($key), '/');
        return $this->publicBase . '/' . $key;
    }

    /**
     * Referencia lista para guardar en BD a partir de una clave ya existente
     * en el almacenamiento (URL publica en R2 o ruta local). No escribe nada.
     */
    public function referencia(string $key): string
    {
        $key = ltrim($this->normalizarKey($key), '/');
        if ($this->isRemote()) {
            return $this->publicBase . '/' . $key;
        }
        return self::LOCAL_PREFIX . '/' . $key;
    }

    /**
     * Convierte una referencia guardada en BD (URL publica de R2 o ruta local)
     * en su clave de almacenamiento. Devuelve null si no es gestionable por este
     * servicio (p. ej. URL absoluta de otro host).
     */
    public function keyDeReferencia(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if ($this->isRemote() && $this->publicBase !== '' && strpos($ref, $this->publicBase . '/') === 0) {
            $key = $this->normalizarKey(substr($ref, strlen($this->publicBase) + 1));
            return $key !== '' ? $key : null;
        }
        if (preg_match('#^https?://#i', $ref)) {
            return null; // URL absoluta que no es de nuestro bucket
        }

        $ref = ltrim(str_replace('\\', '/', $ref), '/');
        if (strpos($ref, self::LOCAL_PREFIX . '/') === 0) {
            $key = $this->normalizarKey(substr($ref, strlen(self::LOCAL_PREFIX) + 1));
            return $key !== '' ? $key : null;
        }
        return null;
    }

    /** Normaliza la clave y rechaza intentos de traversal. '' = invalida. */
    private function normalizarKey(string $key): string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        if (strpos($key, '..') !== false) {
            return '';
        }
        return $key;
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $ruta = $dir . '/' . $item;
            if (is_dir($ruta)) {
                $this->rmdirRecursive($ruta);
            } else {
                @unlink($ruta);
            }
        }
        @rmdir($dir);
    }

    private function moverLocal(string $origen, string $dest): void
    {
        if (!is_dir($origen)) {
            return;
        }
        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        foreach (scandir($origen) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            @rename($origen . '/' . $item, $dest . '/' . $item);
        }
    }
}
