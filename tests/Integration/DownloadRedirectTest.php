<?php
/**
 * tests/Integration/DownloadRedirectTest.php
 * Fase 3 del plan de Drive privado: la descarga local (/home/download) deja de
 * redirigir a drive.google.com y pasa por el proxy firmado con &dl=1, de modo
 * que rom_proxy.php emite Content-Disposition: attachment (una sola ruta de
 * acceso a Drive, compatible con archivos privados).
 */

namespace Tests\Integration;

use UrlSigner;

class DownloadRedirectTest extends IntegrationTestCase {

    private const FILE_ID = 'file-descarga-firmada-1';

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        $GLOBALS['TEST_PDO']->exec(
            "INSERT INTO public.juegos (titulo, google_drive_file_id, activo) "
            . "VALUES ('Descarga Firmada', '" . self::FILE_ID . "', true)"
        );
    }

    /** URL firmada de descarga tal y como la emiten las vistas. */
    private function signedDownloadUrl(string $fileId = self::FILE_ID): string {
        return UrlSigner::downloadUrl($fileId);
    }

    private function downloadsCount(): int {
        $stmt = $this->pdo()->prepare(
            'SELECT downloads_count FROM public.juegos WHERE google_drive_file_id = ?'
        );
        $stmt->execute([self::FILE_ID]);
        return (int) $stmt->fetchColumn();
    }

    public function testDownloadRedirectsToSignedProxyWithDl(): void {
        $before = $this->downloadsCount();
        $resp   = $this->get($this->signedDownloadUrl());

        $this->assertSame(302, $resp['status'], 'La descarga debe responder un 302.');

        $location = $resp['headers']['location'] ?? '';
        $this->assertStringStartsWith('/rom_proxy.php?file_id=', $location);
        $this->assertStringContainsString('file_id=' . self::FILE_ID, $location);
        $this->assertStringContainsString('sig=', $location);
        $this->assertStringContainsString('dl=1', $location);
        $this->assertStringNotContainsString('drive.google.com', $location,
            'No debe exponerse una URL directa de Google Drive.');

        // La URL de destino pasa la validación del propio firmante (misma firma).
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertTrue(UrlSigner::verify(
            (string) $params['file_id'],
            (int) $params['t'],
            (string) $params['sig']
        ));

        $this->assertSame($before + 1, $this->downloadsCount(), 'Debe contarse la descarga.');
    }

    public function testDownloadRejectsInvalidSignature(): void {
        $before = $this->downloadsCount();
        $url    = $this->signedDownloadUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        // Sin firma
        $sinFirma = $this->get('/home/download?file_id=' . self::FILE_ID);
        $this->assertSame(403, $sinFirma['status']);

        // Firma manipulada
        $tamper = $this->get('/home/download?file_id=' . self::FILE_ID
            . '&t=' . $params['t'] . '&sig=' . strrev((string) $params['sig']));
        $this->assertSame(403, $tamper['status']);

        $this->assertSame($before, $this->downloadsCount(),
            'Una petición rechazada no debe contar como descarga.');
    }

    public function testDownloadReturns404ForUnknownFile(): void {
        $resp = $this->get($this->signedDownloadUrl('file-inexistente-9999'));

        $this->assertSame(404, $resp['status']);
    }

    public static function tearDownAfterClass(): void {
        // El rate limit de descargas es por IP y persiste en disco: limpiarlo
        // para no contaminar otras clases de la suite.
        $rateDir = sys_get_temp_dir() . '/rv_rate_limit/download';
        foreach (glob($rateDir . '/*.json') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDownAfterClass();
    }
}
