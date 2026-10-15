<?php

require_once __DIR__ . '/AbstractTranslateProvider.php';

/**
 * config/translation/DeepLTranslateProvider.php
 * Proveedor de DeepL (DeepL API Free / Pro).
 *
 * Es el proveedor recomendado del proyecto: DeepL es un
 * servicio en la nube, así que NO requiere servidor, contenedor ni worker. La
 * traducción se hace con una llamada HTTPS por idioma destino y el plan Free
 * incluye 500 000 caracteres/mes sin tarjeta.
 *
 * Cuenta Free: endpoint api-free.deepl.com y claves que terminan en ':fx'.
 * Cuenta Pro:  endpoint api.deepl.com.
 *
 * API: POST {endpoint}  { text: [..], target_lang, source_lang? }
 *      Cabecera: Authorization: DeepL-Auth-Key <clave>
 *      Respuesta: { translations: [ { detected_source_language, text } ] }
 */

class DeepLTranslateProvider extends AbstractTranslateProvider {
    /** Endpoint del plan Free (default). */
    public const DEFAULT_ENDPOINT = 'https://api-free.deepl.com/v2/translate';

    /** Endpoint del plan Pro (pago). */
    public const PRO_ENDPOINT = 'https://api.deepl.com/v2/translate';

    /**
     * Códigos de la app (I18n::LANGUAGES) -> códigos de DeepL.
     * DeepL usa MAYÚSCULAS y expone el chino simplificado como 'ZH-HANS'.
     */
    private const CODE_MAP = [
        'es' => 'ES',
        'en' => 'EN',
        'fr' => 'FR',
        'pt' => 'PT',
        'de' => 'DE',
        'it' => 'IT',
        'ru' => 'RU',
        'tr' => 'TR',
        'ja' => 'JA',
        'zh' => 'ZH-HANS',
        'ko' => 'KO',
        'ar' => 'AR',
    ];

    private string $apiKey;
    private string $endpoint;

    public function __construct(string $apiKey, int $timeout = 8, string $endpoint = '') {
        parent::__construct($timeout);
        $this->apiKey = trim($apiKey);

        $endpoint = trim($endpoint);
        $this->endpoint = $endpoint !== '' ? rtrim($endpoint, '/') : self::DEFAULT_ENDPOINT;
    }

    public function name(): string {
        return 'deepl';
    }

    public function isConfigured(): bool {
        return $this->apiKey !== '';
    }

    /** DeepL exige el código en MAYÚSCULAS y 'ZH-HANS' para el chino. */
    protected function mapCode(string $codigo): string {
        $codigo = strtolower(trim($codigo));
        return self::CODE_MAP[$codigo] ?? strtoupper($codigo);
    }

    /** DeepL autentica con la cabecera Authorization (no en el cuerpo). */
    protected function extraHeaders(): array {
        return ['Authorization: DeepL-Auth-Key ' . $this->apiKey];
    }

    protected function request(array $textos, string $source, string $target): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('DeepL no configurado (falta TRANSLATE_API_KEY).');
        }

        $payload = [
            'text'        => array_values($textos),
            'target_lang' => $target,
        ];
        // Solo se fija el origen si es un idioma conocido (DeepL lo autodetecta
        // si se omite). La app siempre envía 'es'.
        if ($source !== '') {
            $payload['source_lang'] = $source;
        }

        $datos = $this->postJson($this->endpoint, $payload);
        $traducciones = $datos['translations'] ?? null;

        if (!is_array($traducciones)) {
            throw new RuntimeException('Respuesta de DeepL sin traducciones.');
        }

        $salida = [];
        foreach ($traducciones as $t) {
            $salida[] = is_array($t) ? (string) ($t['text'] ?? '') : '';
        }
        return $salida;
    }
}
