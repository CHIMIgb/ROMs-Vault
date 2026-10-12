<?php
/**
 * tests/Integration/SlugRoutingTest.php
 * Rutas limpias por slug (ítem 7.2): /juego/{slug}, /jugar/{slug},
 * /consolas/{slug} y el 301 desde las URLs legacy (por id y por file_id).
 */

namespace Tests\Integration;

class SlugRoutingTest extends IntegrationTestCase {

    private const JUEGO_SLUG    = 'juego-de-prueba';
    private const CONSOLA_SLUG  = 'teststation';

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        // Datos mínimos: una consola activa y un juego activo con slug.
        $pdo = $GLOBALS['TEST_PDO'];
        $pdo->exec(
            "INSERT INTO public.consolas (id, nombre, slug, activo, emulacion_online) "
            . "VALUES (1, 'TestStation', '" . self::CONSOLA_SLUG . "', TRUE, TRUE) "
            . "ON CONFLICT (id) DO NOTHING"
        );
        $pdo->exec(
            "INSERT INTO public.juegos (id, titulo, slug, consola_id, google_drive_file_id, activo) "
            . "VALUES (1, 'Juego de Prueba', '" . self::JUEGO_SLUG . "', 1, 'FILE123', TRUE) "
            . "ON CONFLICT (id) DO NOTHING"
        );
    }

    // ── Ficha de juego ───────────────────────────────────────────────────

    public function testFichaPorSlugDevuelve200(): void {
        Server::resetCookies();
        $resp = $this->get('/juego/' . self::JUEGO_SLUG);
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Juego de Prueba', $resp['body']);
    }

    public function testSlugInexistenteDevuelve404(): void {
        Server::resetCookies();
        $this->assertSame(404, $this->get('/juego/no-existe')['status']);
    }

    // ── 301 desde las URLs legacy ────────────────────────────────────────

    public function testLegacyShowRedirige301AlSlug(): void {
        Server::resetCookies();
        $resp = $this->get('/home/show/1');
        $this->assertSame(301, $resp['status']);
        $this->assertSame('/juego/' . self::JUEGO_SLUG, $resp['headers']['location'] ?? '');
    }

    public function testLegacyQueryStringShowRedirige301AlSlug(): void {
        Server::resetCookies();
        $resp = $this->get('/index.php?controller=home&action=show&id=1');
        $this->assertSame(301, $resp['status']);
        $this->assertSame('/juego/' . self::JUEGO_SLUG, $resp['headers']['location'] ?? '');
    }

    public function testLegacyPlayRedirige301AlSlug(): void {
        Server::resetCookies();
        $resp = $this->get('/home/play?file_id=FILE123');
        $this->assertSame(301, $resp['status']);
        $this->assertSame('/jugar/' . self::JUEGO_SLUG, $resp['headers']['location'] ?? '');
    }

    // ── Emulador por slug ────────────────────────────────────────────────

    public function testPlayPorSlugDevuelve200(): void {
        Server::resetCookies();
        $resp = $this->get('/jugar/' . self::JUEGO_SLUG);
        $this->assertSame(200, $resp['status']);
    }

    public function testPlaySlugInexistenteNoEsError500(): void {
        Server::resetCookies();
        $resp = $this->get('/jugar/no-existe');
        $this->assertSame(200, $resp['status']); // página de error del emulador
    }

    // ── Catálogo por consola ─────────────────────────────────────────────

    public function testCatalogoPorConsolaDevuelve200(): void {
        Server::resetCookies();
        $resp = $this->get('/consolas/' . self::CONSOLA_SLUG);
        $this->assertSame(200, $resp['status']);
        $this->assertStringContainsString('Juego de Prueba', $resp['body']);
    }

    public function testConsolaInexistenteDevuelve404(): void {
        Server::resetCookies();
        $this->assertSame(404, $this->get('/consolas/no-existe')['status']);
    }
}
