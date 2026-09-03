<?php

namespace franciscoblancojn\LaravelTools;

class LaravelToolsMeta
{
    private ?string $pixelId;
    private ?string $accessToken;
    private string $apiVersion;
    private ?string $testEventCode;

    public function __construct($config = [])
    {
        $this->pixelId = $config['pixelId'] ?? null;
        $this->accessToken = $config['accessToken'] ?? null;
        $this->apiVersion = $config['apiVersion'] ?? 'v26.0';
        $this->testEventCode = $config['testEventCode'] ?? null;
    }

    /**
     * Envía un evento a la Conversions API de Meta.
     * `access_token` va como header Authorization (no en el body) y los
     * campos vacíos/null se limpian antes de enviar, ya que Meta penaliza
     * los campos vacíos en user_data/custom_data.
     */
    public function sendAction($data = [
        "event_name" => "",
        "event_time" => null,
        "action_source" => "website",
        "event_source_url" => null,
        "event_id" => null,
        "user_data" => [],
        "custom_data" => [],
        "test_event_code" => null,
    ])
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pixelId}/events";

        $event = $this->clean([
            "event_name" => $data['event_name'] ?? '',
            "event_time" => $data['event_time'] ?? time(),
            "action_source" => $data['action_source'] ?? 'website',
            "event_source_url" => $data['event_source_url'] ?? null,
            "event_id" => $data['event_id'] ?? null,
            "user_data" => $this->clean($data['user_data'] ?? []),
            "custom_data" => $this->clean($data['custom_data'] ?? []),
        ]);

        $payload = ["data" => [$event]];

        $testEventCode = $data['test_event_code'] ?? $this->testEventCode;
        if ($testEventCode) {
            $payload['test_event_code'] = $testEventCode;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return [
                "ok" => false,
                "http_code" => null,
                "curl_errno" => null,
                "curl_error" => "JSON ERROR: " . json_last_error_msg(),
                "payload" => null,
                "response" => null,
            ];
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);

        $response = curl_exec($curl);

        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $ok = $httpCode === 200 && $curlErrno === 0;

        if (!$ok) {
            // Registrar los fallos: si no, se caen en silencio.
            error_log("[MetaCAPI] fallo evento {$event['event_name']} http={$httpCode} curl={$curlError} resp=" . ($response ?: 'vacío'));
        }

        return [
            "ok" => $ok,
            "http_code" => $httpCode,
            "curl_errno" => $curlErrno,
            "curl_error" => $curlError,
            "payload" => json_decode($json, true),
            "response" => json_decode($response ?: '', true),
        ];
    }

    /**
     * Hash sha256 para los campos PII de user_data (em, ph, etc), como pide Meta.
     */
    public static function hashValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Normaliza un teléfono a formato E.164 sin "+" para hashear en `ph`.
     * Si llegan 10 dígitos (celular colombiano sin indicativo) antepone el
     * indicativo de país por defecto.
     */
    public static function normalizePhone(?string $phone, string $defaultCountryCode = '57'): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = $defaultCountryCode . $digits;
        }

        return $digits;
    }

    /**
     * Quita claves con valor null, string vacío o array vacío.
     */
    private function clean(array $arr): array
    {
        return array_filter($arr, function ($v) {
            return $v !== null && $v !== '' && $v !== [];
        });
    }
}
