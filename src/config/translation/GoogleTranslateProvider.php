<?php

require_once __DIR__ . '/AbstractTranslateProvider.php';

/**
 * config/translation/GoogleTranslateProvider.php
 * Proveedor de Google Cloud Translation (API v2 Basic, autenticada por API key).
 *
 * ALTERNATIVO. El 2026-10-13 Google bloqueó el alta de facturación de este
 * proyecto (filtro antifraude), por lo que hoy NO se usa: el proveedor
 * recomendado es DeepL. Este adaptador queda listo para activarse con
 * TRANSLATE_PROVIDER=google + TRANSLATE_API_KEY cuando se resuelva el bloqueo;
 * la abstracción evita tocar el resto del código.
 *
 * La API key es SOLO servidor (nunca se expone al frontend).
 *
 * API: POST https://translation.googleapis.com/language/translate/v2?key=KEY
 *      { q: [..], source, target, format }
 *      respuesta: { data: { translations: [ { translatedText } ] } }
 */

class GoogleTranslateProvider extends AbstractTranslateProvider {
    private const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    /** Códigos de la app -> BCP-47 de Google. */
    private const CODE_MAP = [
        'zh' => 'zh-CN',
    ];

    private string $apiKey;

    public function __construct(string $apiKey, int $timeout = 8) {
        parent::__construct($timeout);
        $this->apiKey = trim($apiKey);
    }

    public function name(): string {
        return 'google';
    }

    public function isConfigured(): bool {
        return $this->apiKey !== '';
    }

    protected function mapCode(string $codigo): string {
        return self::CODE_MAP[$codigo] ?? $codigo;
    }

    protected function request(array $textos, string $source, string $target): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Translate no configurado (falta TRANSLATE_API_KEY).');
        }

        $url = self::ENDPOINT . '?key=' . rawurlencode($this->apiKey);
        $datos = $this->postJson($url, [
            'q'      => $textos,
            'source' => $source,
            'target' => $target,
            'format' => 'text',
        ]);

        $traducciones = $datos['data']['translations'] ?? null;
        if (!is_array($traducciones)) {
            throw new RuntimeException('Respuesta de Google Translate sin traducciones.');
        }

        $salida = [];
        foreach ($traducciones as $t) {
            $salida[] = html_entity_decode(
                (string) ($t['translatedText'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }
        return $salida;
    }
}
