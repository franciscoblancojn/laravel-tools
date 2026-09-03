<?php

namespace franciscoblancojn\LaravelTools;

class LaravelToolsGoogle
{
    private ?string $measurementId;
    private ?string $apiSecret;
    private array $ads;

    public function __construct($config = [])
    {
        $this->measurementId = $config['measurementId'] ?? null;
        $this->apiSecret = $config['apiSecret'] ?? null;
        $this->ads = $config['ads'] ?? [];
    }

    /**
     * GA4 Measurement Protocol. Solo alimenta reportes de GA4, no mueve
     * conversiones de Google Ads (para eso usar sendAdsOfflineConversion).
     */
    public function sendAction($data = [
        "client_id" => "",
        "event_name" => "",
        "params" => [],
    ])
    {
        $url = "https://www.google-analytics.com/mp/collect"
            . "?measurement_id=" . urlencode((string) $this->measurementId)
            . "&api_secret=" . urlencode((string) $this->apiSecret);

        $payload = [
            "client_id" => $data['client_id'] ?? '',
            "events" => [
                [
                    "name" => $data['event_name'] ?? '',
                    "params" => $data['params'] ?? [],
                ],
            ],
        ];

        return $this->post($url, $payload);
    }

    /**
     * Sube una conversión offline (por GCLID) a Google Ads vía Data Manager
     * API. Requiere credenciales OAuth (client id/secret + refresh token)
     * con el scope https://www.googleapis.com/auth/datamanager.
     */
    public function sendAdsOfflineConversion($data = [
        "conversion_action_id" => null,
        "gclid" => null,
        "event_timestamp" => null,
        "transaction_id" => null,
        "conversion_value" => 0,
        "currency_code" => "COP",
    ])
    {
        $gclid = $data['gclid'] ?? null;

        if (empty($gclid)) {
            return ["ok" => false, "error" => "gclid vacío: no se puede subir la conversión sin GCLID"];
        }

        $conversionActionId = $data['conversion_action_id'] ?? ($this->ads['conversionActionId'] ?? null);

        if (empty($conversionActionId)) {
            return ["ok" => false, "error" => "conversion_action_id vacío"];
        }

        $customerId = $this->ads['customerId'] ?? null;

        if (empty($customerId)) {
            return ["ok" => false, "error" => "customerId de Google Ads vacío"];
        }

        $tokenResult = $this->getAdsAccessToken();

        if (!($tokenResult['ok'] ?? false)) {
            return ["ok" => false, "error" => "No se pudo obtener access_token de Google Ads", "details" => $tokenResult];
        }

        $destination = [
            'operatingAccount' => [
                'accountType' => 'GOOGLE_ADS',
                'accountId' => $customerId,
            ],
            'productDestinationId' => (string) $conversionActionId,
        ];

        if (!empty($this->ads['loginCustomerId'])) {
            $destination['loginAccount'] = [
                'accountType' => 'GOOGLE_ADS',
                'accountId' => $this->ads['loginCustomerId'],
            ];
        }

        $payload = [
            'destinations' => [$destination],
            'events' => [
                [
                    'eventTimestamp' => $data['event_timestamp'] ?? gmdate('Y-m-d\TH:i:sP'),
                    'transactionId' => $data['transaction_id'] ?? uniqid('conv_', true),
                    'conversionValue' => $data['conversion_value'] ?? 0,
                    'currency' => $data['currency_code'] ?? 'COP',
                    'eventSource' => 'WEB',
                    'adIdentifiers' => [
                        'gclid' => $gclid,
                    ],
                ],
            ],
            'validateOnly' => false,
        ];

        return $this->post(
            'https://datamanager.googleapis.com/v1/events:ingest',
            $payload,
            ["Authorization: Bearer {$tokenResult['access_token']}"]
        );
    }

    /**
     * Lista las acciones de conversión de la cuenta de Ads con su ID real
     * (el que pide sendAdsOfflineConversion). Útil para configurar
     * `conversionActionId` sin entrar a la consola de Google Ads.
     */
    public function listAdsConversionActions()
    {
        $tokenResult = $this->getAdsAccessToken();

        if (!($tokenResult['ok'] ?? false)) {
            return ["ok" => false, "error" => "No se pudo obtener access_token de Google Ads", "details" => $tokenResult];
        }

        $customerId = $this->ads['customerId'] ?? null;
        $apiVersion = $this->ads['apiVersion'] ?? 'v25';

        $headers = [
            "Authorization: Bearer {$tokenResult['access_token']}",
            "developer-token: " . ($this->ads['developerToken'] ?? ''),
        ];

        if (!empty($this->ads['loginCustomerId'])) {
            $headers[] = "login-customer-id: " . $this->ads['loginCustomerId'];
        }

        return $this->post(
            "https://googleads.googleapis.com/{$apiVersion}/customers/{$customerId}/googleAds:search",
            ['query' => 'SELECT conversion_action.id, conversion_action.name, conversion_action.status FROM conversion_action'],
            $headers
        );
    }

    /**
     * Intercambia el refresh_token por un access_token de OAuth2 para las
     * APIs de Google Ads / Data Manager.
     */
    private function getAdsAccessToken()
    {
        $curl = curl_init('https://oauth2.googleapis.com/token');

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->ads['clientId'] ?? '',
                'client_secret' => $this->ads['clientSecret'] ?? '',
                'refresh_token' => $this->ads['refreshToken'] ?? '',
                'grant_type' => 'refresh_token',
            ]),
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response' => json_decode($response ?: '', true),
            ];
        }

        $decoded = json_decode($response, true);

        return ['ok' => true, 'access_token' => $decoded['access_token'] ?? null];
    }

    private function post(string $url, array $payload, array $extraHeaders = [])
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return [
                "ok" => false,
                "http_code" => null,
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
            CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"], $extraHeaders),
            CURLOPT_POSTFIELDS => $json,
        ]);

        $response = curl_exec($curl);

        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        $ok = $httpCode >= 200 && $httpCode < 300 && $curlErrno === 0;

        if (!$ok) {
            error_log("[GoogleAPI] fallo POST {$url} http={$httpCode} curl={$curlError} resp=" . ($response ?: 'vacío'));
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
}
