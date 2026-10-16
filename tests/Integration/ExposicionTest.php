<?php
/**
 * tests/Integration/ExposicionTest.php
 * Rutas internas que NUNCA deben servirse por HTTP (credenciales, datos, código
 * y documentación). Se comprueba contra el servidor real con `router.php`, que
 * aplica las mismas denegaciones que `.htaccess` en Apache.
 *
 * Además del código de estado, cada assert verifica que la respuesta no filtre
 * el contenido: un 200 con el .env es exactamente el fallo que se persigue.
 *
 * Contexto y evidencia: docs/2026-10-16-endurecimiento-exposicion-http.md
 */

namespace Tests\Integration;

class ExposicionTest extends IntegrationTestCase {

    public function testEnvNoSeSirve(): void {
        $resp = $this->get('/.env');
        $this->assertSame(403, $resp['status']);
        $this->assertStringNotContainsString('DB_PASSWORD', (string) $resp['body']);
        $this->assertStringNotContainsString('JWT_SECRET', (string) $resp['body']);
    }

    public function testCredencialesDeLaCuentaDeServicioNoSeSirven(): void {
        $resp = $this->get('/.secrets/service-account.json');
        $this->assertSame(403, $resp['status']);
        $this->assertStringNotContainsString('private_key', (string) $resp['body']);
    }

    public function testMetadatosDeGitYOtrosDotfilesNoSeSirven(): void {
        foreach (['/.git/config', '/.gitignore', '/.editorconfig', '/.dockerignore'] as $ruta) {
            $this->assertSame(403, $this->get($ruta)['status'], $ruta);
        }
    }

    public function testSqlDeDatosNoSeSirve(): void {
        $this->assertSame(403, $this->get('/data/roms-vaultDB-postgreSQL.sql')['status']);
        $this->assertSame(403, $this->get('/data/import_data.sql')['status']);
    }

    public function testCodigoDeLaAppNoSeSirve(): void {
        $resp = $this->get('/src/config/database.php');
        $this->assertSame(403, $resp['status']);
        $this->assertStringNotContainsString('<?php', (string) $resp['body']);
    }

    public function testFicherosDeConfiguracionNoSeSirven(): void {
        $rutas = [
            '/phpunit.xml',
            '/composer.json',
            '/Dockerfile',
            '/docker-entrypoint.sh',
            '/router.php',
            '/AGENTS.md',
            '/README.md',
        ];
        foreach ($rutas as $ruta) {
            $this->assertSame(403, $this->get($ruta)['status'], $ruta);
        }
    }

    public function testDirectoriosInternosNoSeSirven(): void {
        $rutas = [
            '/tests/Integration/Server.php',
            '/tests/.env.test.example',
            '/scratch/smoke_fase5.php',
            '/docs',
            '/vendor',
        ];
        foreach ($rutas as $ruta) {
            $this->assertSame(403, $this->get($ruta)['status'], $ruta);
        }
    }

    public function testLaAppSigueSirviendoLoPublico(): void {
        $this->assertSame(200, $this->get('/')['status']);
        $this->assertSame(200, $this->get('/public/css/style.css')['status']);
        $this->assertSame(200, $this->get('/public/img/icon.png')['status']);
    }

    public function testElProxySigueAlcanzablePorSuUrlPublica(): void {
        // La URL pública de endpoints/rom_proxy.php sigue mapeada (no la bloquea
        // el endurecimiento) y responde con su propio error JSON al no ir firmada.
        $resp = $this->get('/rom_proxy.php?file_id=1ls81axzKHrOYwhl6H8YFeUeT72GbFoLG');
        $this->assertSame(403, $resp['status']);
        $this->assertStringContainsString('error', (string) $resp['body']);
    }
}
