<?php
require_once __DIR__ . '/AbstractTranslateProvider.php';

/**
 * config/translation/LibreTranslateProvider.php
 * Proveedor de LibreTranslate (auto-hospedado o instancia pública).
 *
 * Es la opción GRATUITA por defecto del proyecto: no requiere facturación ni
 * tarjeta. Como las traducciones se guardan en la base de datos, el servicio
 * solo se necesita AL TRADUCIR (crear/editar/backfill), NO en producción.
 *
 * Arranque recomendado (una vez, en la máquina del autor):
 *   docker run -ti --rm -p 5000:5000 \
 *     -e LT_LOAD_ONLY=es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar \
 *     libretranslate/libretranslate
 * y en .env:  TRANSLATE_PROVIDER=libretranslate
 *             TRANSLATE_LIBRE_URL=http://localhost:5000
 *
 * API: POST {url}/translate  { q: [..], source, target, format, api_key? }
 *      respuesta: { translatedText: [..] }  (o string si q fue string)
 */

class LibreTranslateProvider extends AbstractTranslateProvider {

    private string $url;
    private string $apiKey;

    public function __construct(string $url, string $apiKey = '', int $timeout = 8) {
        parent::__construct($timeout);
        $this->url    = rtrim(trim($url), '/');
        $this->apiKey = trim($apiKey);
    }

    public function name(): string {
        return 'libretranslate';
    }

    public function isConfigured(): bool {
        return $this->url !== '';
    }

    protected function request(array $textos, string $source, string $target): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('LibreTranslate no configurado (falta TRANSLATE_LIBRE_URL).');
        }

        $payload = [
            'q'      => $textos,
            'source' => $source,
            'target' => $target,
            'format' => 'text',
        ];
        if ($this->apiKey !== '') {
            $payload['api_key'] = $this->apiKey;
        }

        $datos = $this->postJson($this->url . '/translate', $payload);
        $texto = $datos['translatedText'] ?? null;

        if (is_array($texto)) {
            return array_map('strval', $texto);
        }
        if (is_string($texto)) {
            return [$texto];
        }
        throw new RuntimeException('Respuesta de LibreTranslate sin translatedText.');
    }
}
