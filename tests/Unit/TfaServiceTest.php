<?php
/**
 * tests/Unit/TfaServiceTest.php
 * Unit tests del TOTP (RFC 6238) y del estado pendiente de 2FA.
 *
 * Los vectores de código son los del Apéndice B del RFC 6238 (SHA-1), con
 * clave = bytes ASCII "12345678901234567890". Como TfaService recibe el
 * secreto en base32, se pasa la codificación base32 de esa clave.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TfaService;

class TfaServiceTest extends TestCase {

    /** Secreto base32 equivalente a la clave ASCII del RFC 6238. */
    private static function rfcSecret(): string {
        return TfaService::base32Encode('12345678901234567890');
    }

    public function testVectoresRfc6238(): void {
        // Apéndice B del RFC 6238 — vectores de referencia SHA-1 con 8 dígitos
        $vectors = [
            59          => '94287082',
            1111111109  => '07081804',
            1111111111  => '14050471',
            1234567890  => '89005924',
            2000000000  => '69279037',
            20000000000 => '65353130',
        ];

        $secret = self::rfcSecret();
        foreach ($vectors as $t => $esperado) {
            $this->assertSame($esperado, TfaService::codigo($secret, $t, 8), "T=$t");
        }
    }

    public function testGenerarSecretEsBase32ValidoDe32Caracteres(): void {
        $secret = TfaService::generarSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertNotNull(TfaService::base32Decode($secret));
    }

    public function testBase32Roundtrip(): void {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, TfaService::base32Decode(TfaService::base32Encode($bytes)));
    }

    public function testBase32RechazaCaracteresInvalidos(): void {
        $this->assertNull(TfaService::base32Decode('ABC0!@#'));
    }

    public function testVerificarAceptaCodigoActual(): void {
        $secret = TfaService::generarSecret();
        $code = TfaService::codigo($secret);
        $this->assertTrue(TfaService::verificar($secret, $code));
    }

    public function testVerificarRechazaCodigoIncorrecto(): void {
        $secret = TfaService::generarSecret();
        $this->assertFalse(TfaService::verificar($secret, '000000'));
    }

    public function testVerificarRechazaFormatoInvalido(): void {
        $secret = TfaService::generarSecret();
        $this->assertFalse(TfaService::verificar($secret, ''));
        $this->assertFalse(TfaService::verificar($secret, '12345'));
        $this->assertFalse(TfaService::verificar($secret, 'abcdef'));
        $this->assertFalse(TfaService::verificar($secret, '1234567'));
    }

    public function testVerificarConLeewayAceptaCodigoConDesfaseDe30Segundos(): void {
        $secret = TfaService::generarSecret();
        $code30sAntes = TfaService::codigo($secret, time() - 30);
        $this->assertTrue(TfaService::verificar($secret, $code30sAntes, 1));
    }

    public function testProvisionUriContieneSecretEIssuer(): void {
        $secret = TfaService::generarSecret();
        $uri = TfaService::provisionUri('admin', $secret);
        $this->assertStringContainsString('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . rawurlencode($secret), $uri);
        $this->assertStringContainsString('issuer=ROMs%20Vault', $uri);
        $this->assertStringContainsString('algorithm=SHA1&digits=6&period=30', $uri);
    }

    public function testUsuarioPendienteRechazaFirmaInvalida(): void {
        $_ENV['JWT_SECRET'] = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';
        unset($_COOKIE['rv_tfa_pending']);
        $this->assertNull(TfaService::usuarioPendiente());

        // Firma manipulada
        $_COOKIE['rv_tfa_pending'] = '1:' . (time() + 60) . ':firma-incorrecta';
        $this->assertNull(TfaService::usuarioPendiente());
    }

    public function testUsuarioPendienteAceptaFirmaValidaYDesechaExpirada(): void {
        $key = 'secret-de-prueba-phpunit-2026-muy-largo-y-seguro';
        $_ENV['JWT_SECRET'] = $key;
        unset($_COOKIE['rv_tfa_pending']);

        $exp = time() + 60;
        $payload = '7:' . $exp;
        $sig = hash_hmac('sha256', $payload, $key);
        $_COOKIE['rv_tfa_pending'] = $payload . ':' . $sig;
        $this->assertSame(7, TfaService::usuarioPendiente());

        // Expirada: el mismo token con exp en el pasado es rechazado
        $payloadExp = '7:' . (time() - 60);
        $sigExp = hash_hmac('sha256', $payloadExp, $key);
        $_COOKIE['rv_tfa_pending'] = $payloadExp . ':' . $sigExp;
        $this->assertNull(TfaService::usuarioPendiente());
    }
}