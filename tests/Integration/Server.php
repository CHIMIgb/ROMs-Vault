<?php
/**
 * tests/Integration/Server.php
 * Helper de integración: levanta el servidor PHP embebido (php -S ... router.php)
 * como proceso hijo con variables de entorno que apuntan a la BD de prueba,
 * y hace requests HTTP reales manteniendo las cookies del "navegador".
 *
 * Patrón estándar para testear una app MVC PHP sin framework sin tocar su
 * arquitectura (los controladores usan header()/exit/setcookie, que solo se
 * pueden probar con un HTTP server real).
 *
 * Maneja el flujo CSRF de la app (Double Submit Cookie): al hacer GET a una
 * página se captura la cookie rv_csrf y se reenvía; los tests deben incluir
 * su valor como csrf_token en el body de cada POST (Server::csrfToken()).
 */

namespace Tests\Integration;

class Server {

    private const HOST = '127.0.0.1';
    private const PORT = 8099;

    /** @var resource|null Proceso del servidor */
    private static $process = null;
    private static int $port = 0;
    private static ?string $logFile = null;

    /** Cookies del "navegador" de prueba (nombre => valor). */
    private static array $cookies = [];

    /**
     * Arranca el servidor php -S con router.php y el entorno de la BD de prueba.
     */
    public static function start(): void {
        if (self::$process !== null) {
            return;
        }

        $root = dirname(__DIR__, 2);
        $php  = PHP_BINARY; // php.exe con el que corre PHPUnit (Windows o WSL)
        self::$port = self::freePort();

        // Entorno para el servidor hijo: apunta a la BD de prueba, nunca a
        // producción. Se parte del entorno actual y se sobrescriben las vars.
        // TEST_DB_PASSWORD lo valida y define tests/Integration/bootstrap.php.
        $env = array_merge(getenv(), [
            'DB_HOST'         => '127.0.0.1',
            'DB_PORT'         => '5432',
            'DB_NAME'         => 'roms-vault-test',
            'DB_USER'         => 'postgres',
            'DB_PASSWORD'     => getenv('TEST_DB_PASSWORD') ?: '',
            'DB_SSLMODE'      => 'disable',
            'JWT_SECRET'      => 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro',
            'JWT_EXPIRATION'  => '3600',
        ]);

        // Comando como ARRAY (PHP 7.4+): evita que Windows meta cmd.exe de
        // por medio. Con string, proc_terminate() mata a cmd.exe pero el
        // php -S real queda huérfano y proc_close() se cuelga al final.
        //
        // variables_order=EGPCS: con el default "GPCS" el $_ENV del proceso
        // hijo arranca vacío y Dotenv lo rellena con el .env real (Neon).
        // Como config/database.php prioriza $_ENV sobre getenv(), el hijo
        // conectaría a producción en lugar de a la BD de prueba. Con "EGPCS"
        // $_ENV se puebla desde el entorno real que le pasamos por proc_open
        // y Dotenv::createImmutable no sobrescribe esas variables.
        $cmd = [
            $php,
            '-d', 'display_errors=1',
            '-d', 'variables_order=EGPCS',
            '-S', self::HOST . ':' . self::$port,
            $root . '/router.php',
        ];

        // Sin pipes: stdout/stderr del servidor van a un log temporal.
        // En Windows los handles de pipe heredados mantienen vivo a
        // proc_close() y el proceso PHPUnit nunca termina. Con archivos
        // el cierre es limpio.
        self::$logFile = sys_get_temp_dir() . '/rv-integration-' . getmypid() . '.log';
        $descriptors = [
            0 => ['file', 'NUL', 'r'],          // stdin cerrado (NUL en Windows)
            1 => ['file', self::$logFile, 'a'], // stdout → log
            2 => ['file', self::$logFile, 'a'], // stderr → log
        ];

        self::$process = proc_open($cmd, $descriptors, $unusedPipes, $root, $env);

        if (!is_resource(self::$process)) {
            throw new \RuntimeException('No se pudo iniciar el servidor de prueba.');
        }

        // Esperar a que el servidor acepte conexiones (máx. 5s)
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline && !self::isListening()) {
            usleep(50_000);
        }
        if (!self::isListening()) {
            $log = self::$logFile && is_file(self::$logFile)
                ? file_get_contents(self::$logFile)
                : '(sin log)';
            self::stop();
            throw new \RuntimeException('El servidor de prueba no arrancó a tiempo. Log: ' . $log);
        }
    }

    /** Puerto libre en 127.0.0.1 (evita choques con el servidor de desarrollo). */
    private static function freePort(): int {
        $sock = @stream_socket_server('tcp://' . self::HOST . ':0', $errno, $errstr);
        if (!$sock) {
            return self::PORT;
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        $port = (int) substr((string) strrchr((string) $name, ':'), 1);
        return $port > 0 ? $port : self::PORT;
    }

    public static function stop(): void {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
        if (self::$logFile && is_file(self::$logFile)) {
            @unlink(self::$logFile);
        }
        self::$logFile = null;
    }

    private static function isListening(): bool {
        $conn = @stream_socket_client(
            'tcp://' . self::HOST . ':' . self::$port,
            $errno,
            $errstr,
            0.3
        );
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    /** Limpia las cookies capturadas (equivalente a un navegador "nuevo"). */
    public static function resetCookies(): void {
        self::$cookies = [];
    }

    /** Valor de la cookie CSRF capturada (para incluir como csrf_token en POST). */
    public static function csrfToken(): string {
        return self::$cookies['rv_csrf'] ?? '';
    }

    /** Cookie de sesión JWT capturada (rv_token). */
    public static function sessionToken(): string {
        return self::$cookies['rv_token'] ?? '';
    }

    public static function get(string $path, array $headers = []): array {
        return self::request('GET', $path, null, $headers);
    }

    public static function post(string $path, array $data, array $headers = []): array {
        return self::request('POST', $path, $data, $headers);
    }

    /**
     * Request HTTP genérico. Devuelve [status, headers, body, cookies].
     * No sigue redirects (queremos inspeccionar Location).
     */
    public static function request(string $method, string $path, ?array $data, array $headers = []): array {
        $url = 'http://' . self::HOST . ':' . self::$port . $path;

        $opts = [
            'http' => [
                'method'         => $method,
                'header'         => self::buildHeaders($headers, $data !== null),
                'ignore_errors'  => true, // no tratar 4xx/5xx como error de stream
                'follow_location' => 0,   // NO seguir redirects
                'timeout'        => 10,   // evitar cuelgues si el server se bloquea
            ],
        ];

        if ($data !== null) {
            $opts['http']['content'] = http_build_query($data);
        }

        $ctx = stream_context_create($opts);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException("Request fallido: $method $path");
        }

        $rawHeaders = $http_response_header ?? [];
        $status = 0;
        $responseHeaders = [];
        $setCookies = [];

        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\d(?:\.\d)? (\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
                if (strtolower(trim($name)) === 'set-cookie') {
                    if (preg_match('/^([^=]+)=([^;]*)/', trim($value), $cm)) {
                        $setCookies[$cm[1]] = $cm[2];
                    }
                }
            }
        }

        // Actualizar las cookies del "navegador" de prueba
        foreach ($setCookies as $name => $value) {
            if ($value === '') {
                unset(self::$cookies[$name]); // cookie expirada/eliminada
            } else {
                self::$cookies[$name] = $value;
            }
        }

        return [
            'status'  => $status,
            'headers' => $responseHeaders,
            'body'    => $body,
            'cookies' => $setCookies,
        ];
    }

    private static function buildHeaders(array $headers, bool $isPost = false): string {
        $lines = ["User-Agent: PHPUnit-Integration", "Accept: text/html"];
        // Content-Type para POST (PHP solo puebla $_POST si el header lo indica)
        if ($isPost) {
            $lines[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        foreach ($headers as $name => $value) {
            $lines[] = "$name: $value";
        }
        if (self::$cookies !== []) {
            $cookieLine = [];
            foreach (self::$cookies as $name => $value) {
                $cookieLine[] = "$name=$value";
            }
            $lines[] = 'Cookie: ' . implode('; ', $cookieLine);
        }
        return implode("\r\n", $lines) . "\r\n";
    }
}
