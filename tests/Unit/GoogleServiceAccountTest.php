<?php
/**
 * tests/Unit/GoogleServiceAccountTest.php
 * Unit tests de la cuenta de servicio de Google (config/GoogleServiceAccount.php).
 *
 * Todo es offline: la firma RS256 se verifica con un par de claves fijo de
 * usar y tirar (fixtures en base64 bajo Support/); no se llama a la red, no se
 * usan credenciales reales ni se depende de openssl.cnf (ausente en XAMPP/Windows).
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use GoogleServiceAccount;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class GoogleServiceAccountTest extends TestCase {

    /** @var array<string, mixed> */
    private array $envOriginal = [];

    /** Clave privada PEM del par de pruebas. */
    private string $privatePem = '';

    /** Clave pública PEM del par de pruebas. */
    private string $publicPem = '';

    protected function setUp(): void {
        parent::setUp();

        foreach (['GOOGLE_SERVICE_ACCOUNT_FILE', 'GOOGLE_SERVICE_ACCOUNT_JSON_B64'] as $key) {
            $this->envOriginal[$key] = $_ENV[$key] ?? '__SIN_SET__';
            unset($_ENV[$key]);
        }

        // Par RSA 2048 fijo de usar y tirar (fixtures en base64, nunca reales).
        $this->privatePem = (string) base64_decode(
            (string) file_get_contents(__DIR__ . '/Support/rsa_test_private.pem.b64')
        );
        $this->publicPem = (string) base64_decode(
            (string) file_get_contents(__DIR__ . '/Support/rsa_test_public.pem.b64')
        );

        GoogleServiceAccount::clearCache();
    }

    protected function tearDown(): void {
        foreach ($this->envOriginal as $key => $value) {
            if ($value === '__SIN_SET__') {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        GoogleServiceAccount::clearCache();
        JWT::$timestamp = null;
        parent::tearDown();
    }

    /** @return array{client_email:string, private_key:string, token_uri:string} */
    private function credenciales(): array {
        return [
            'client_email' => 'roms-vault-proxy@roms-vault.iam.gserviceaccount.com',
            'private_key'  => $this->privatePem,
            'token_uri'    => 'https://oauth2.googleapis.com/token',
        ];
    }

    public function testSinCredencialesNoEstaConfigurada(): void {
        $this->assertFalse(GoogleServiceAccount::isConfigured());
        $this->assertNull(GoogleServiceAccount::credentials());
        $this->assertNull(GoogleServiceAccount::accessToken());
    }

    public function testBuildAssertionFirmaRs256ConClaimsCorrectos(): void {
        $now = 1700000000;
        // Fijar el reloj de php-jwt para que un iat/exp fijo no caduque.
        JWT::$timestamp = $now;
        $jwt = GoogleServiceAccount::buildAssertion($this->credenciales(), $now);

        // Cabecera del JWT (primer segmento) para comprobar el algoritmo.
        $header = json_decode(base64_decode(strtr(explode('.', $jwt)[0], '-_', '+/')), true);
        $this->assertSame('RS256', $header['alg']);

        // El payload debe verificar contra la clave publica con php-jwt.
        $claims = JWT::decode($jwt, new Key($this->publicPem, 'RS256'));

        $this->assertSame('roms-vault-proxy@roms-vault.iam.gserviceaccount.com', $claims->iss);
        $this->assertSame('https://www.googleapis.com/auth/drive.readonly', $claims->scope);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims->aud);
        $this->assertSame($now, $claims->iat);
        $this->assertSame($now + 3600, $claims->exp);
    }

    public function testParseCredentialsRechazaJsonInvalido(): void {
        $this->assertNull(GoogleServiceAccount::parseCredentials('no-json'));
        $this->assertNull(GoogleServiceAccount::parseCredentials('{}'));
        $this->assertNull(GoogleServiceAccount::parseCredentials('{"client_email":"a@b.iam.gserviceaccount.com"}'));
        $this->assertNull(GoogleServiceAccount::parseCredentials('{"private_key":"PEM"}'));
    }

    public function testParseCredentialsUsaTokenUriPorDefecto(): void {
        $creds = GoogleServiceAccount::parseCredentials(json_encode([
            'client_email' => 'a@b.iam.gserviceaccount.com',
            'private_key'  => 'PEM',
        ]));

        $this->assertNotNull($creds);
        $this->assertSame('https://oauth2.googleapis.com/token', $creds['token_uri']);
    }

    public function testCredentialsCargaDesdeArchivo(): void {
        $file = sys_get_temp_dir() . '/rv_gsa_test_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($file, json_encode($this->credenciales()));

        try {
            $_ENV['GOOGLE_SERVICE_ACCOUNT_FILE'] = $file;

            $this->assertTrue(GoogleServiceAccount::isConfigured());
            $creds = GoogleServiceAccount::credentials();
            $this->assertNotNull($creds);
            $this->assertSame('roms-vault-proxy@roms-vault.iam.gserviceaccount.com', $creds['client_email']);
            $this->assertSame($this->privatePem, $creds['private_key']);
        } finally {
            @unlink($file);
        }
    }

    public function testCredentialsCargaDesdeBase64(): void {
        $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON_B64'] = base64_encode(json_encode($this->credenciales()));

        $this->assertTrue(GoogleServiceAccount::isConfigured());
        $creds = GoogleServiceAccount::credentials();
        $this->assertNotNull($creds);
        $this->assertSame($this->privatePem, $creds['private_key']);
    }

    public function testBase64EnBase64InvalidoNoConfigura(): void {
        $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON_B64'] = '!!!no-es-base64!!!';
        $this->assertFalse(GoogleServiceAccount::isConfigured());
    }

    public function testArchivoTienePrioridadSobreBase64(): void {
        $file = sys_get_temp_dir() . '/rv_gsa_test_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($file, json_encode([
            'client_email' => 'desde-archivo@proj.iam.gserviceaccount.com',
            'private_key'  => 'PEM',
        ]));

        try {
            $_ENV['GOOGLE_SERVICE_ACCOUNT_FILE']      = $file;
            $_ENV['GOOGLE_SERVICE_ACCOUNT_JSON_B64']  = base64_encode(json_encode([
                'client_email' => 'desde-base64@proj.iam.gserviceaccount.com',
                'private_key'  => 'PEM',
            ]));

            $this->assertSame(
                'desde-archivo@proj.iam.gserviceaccount.com',
                GoogleServiceAccount::credentials()['client_email']
            );
        } finally {
            @unlink($file);
        }
    }
}
