<?php
/**
 * tests/Unit/DrenadoTriggerTest.php
 * Disparador del drenado de traducciones (config/DrenadoTrigger.php):
 *   - construirPeticion() arma la petición HTTP cruda (ruta, secreto, cierre);
 *   - disponible()/disparar() son inertes sin secreto o sin proveedor;
 *   - urlInterna() deriva del puerto local y respeta el override por env.
 *
 * Son tests puros: no abren sockets ni tocan la BD (disparar() sale antes por el
 * guard de proveedor cuando corresponde).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DrenadoTriggerTest extends TestCase {

    /**
     * Ejecuta $fn con las variables de entorno indicadas y las restaura después.
     * Escribe en $_ENV y putenv() porque las factories leen $_ENV primero.
     */
    private function conEnv(array $vars, callable $fn) {
        $previos = [];
        foreach ($vars as $clave => $valor) {
            $previos[$clave] = $_ENV[$clave] ?? null;
            $_ENV[$clave] = $valor;
            putenv("$clave=$valor");
        }

        try {
            return $fn();
        } finally {
            foreach ($previos as $clave => $valor) {
                if ($valor === null) {
                    unset($_ENV[$clave]);
                    putenv($clave);
                } else {
                    $_ENV[$clave] = $valor;
                    putenv("$clave=$valor");
                }
            }
        }
    }

    public function testConstruirPeticionIncluyeRutaSecretoYCierre(): void {
        $peticion = \DrenadoTrigger::construirPeticion(
            's3cr3t',
            'http://127.0.0.1:8099/endpoints/drain_traducciones.php'
        );

        $this->assertStringStartsWith('GET /endpoints/drain_traducciones.php HTTP/1.1', $peticion);
        $this->assertStringContainsString('X-Internal-Drain: s3cr3t', $peticion);
        $this->assertStringContainsString('Connection: close', $peticion);
        $this->assertStringEndsWith("\r\n\r\n", $peticion);
    }

    public function testConstruirPeticionUsaLaRutaPorDefecto(): void {
        $peticion = \DrenadoTrigger::construirPeticion('s3cr3t', 'http://127.0.0.1:80');

        $this->assertStringStartsWith('GET /endpoints/drain_traducciones.php HTTP/1.1', $peticion);
    }

    public function testDisponibleConSecretoYProveedor(): void {
        $this->conEnv([
            'INTERNAL_DRAIN_SECRET' => 's3cr3t',
            'TRANSLATE_PROVIDER'    => 'deepl',
            'TRANSLATE_API_KEY'     => 'clave-falsa',
        ], function () {
            $this->assertTrue(\DrenadoTrigger::disponible());
        });
    }

    public function testDispararSinSecretoEsInerte(): void {
        $this->conEnv([
            'INTERNAL_DRAIN_SECRET' => '',
            'TRANSLATE_PROVIDER'    => 'deepl',
            'TRANSLATE_API_KEY'     => 'clave-falsa',
        ], function () {
            $this->assertFalse(\DrenadoTrigger::disponible());
            $this->assertFalse(\DrenadoTrigger::disparar());
        });
    }

    public function testDispararSinProveedorEsInerte(): void {
        $this->conEnv([
            'INTERNAL_DRAIN_SECRET' => 's3cr3t',
            'TRANSLATE_PROVIDER'    => 'none',
            'TRANSLATE_API_KEY'     => '',
        ], function () {
            $this->assertFalse(\DrenadoTrigger::disponible());
            $this->assertFalse(\DrenadoTrigger::disparar());
        });
    }

    public function testUrlInternaDerivaDelPuertoLocal(): void {
        $previo = $_SERVER['SERVER_PORT'] ?? null;
        $_SERVER['SERVER_PORT'] = '8080';

        try {
            $this->conEnv(['INTERNAL_DRAIN_URL' => ''], function () {
                $this->assertSame(
                    'http://127.0.0.1:8080/endpoints/drain_traducciones.php',
                    \DrenadoTrigger::urlInterna()
                );
            });
        } finally {
            if ($previo === null) {
                unset($_SERVER['SERVER_PORT']);
            } else {
                $_SERVER['SERVER_PORT'] = $previo;
            }
        }
    }

    public function testUrlInternaRespetaElOverride(): void {
        $this->conEnv(['INTERNAL_DRAIN_URL' => 'http://127.0.0.1:9999/x.php'], function () {
            $this->assertSame('http://127.0.0.1:9999/x.php', \DrenadoTrigger::urlInterna());
        });
    }
}
