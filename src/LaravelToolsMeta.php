<?php

namespace franciscoblancojn\LaravelTools;


class LaravelToolsMeta
{
    private string $pixelId;
    private string $accessToken;
    private string $apiVersion;

    public function __construct($config = [])
    {
        $this->pixelId = isset($config['pixelId']) ? $config['pixelId'] : null;
        $this->accessToken = isset($config['accessToken']) ? $config['accessToken'] : null;
        $this->apiVersion = isset($config['apiVersion']) ? $config['apiVersion'] : 'v26.0';
    }

    function sendAction($data = [
        "event_name" => "",
        "event_time" => null,
        "action_source" => "website",
        "event_source_url" => null,
        "event_id" => null,
        "user_data" => [],
        "custom_data" => [],
    ])
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->pixelId}/events";

        $payload = [
            "data" => [
                [
                    "event_name" => $data['event_name'] ?? '',
                    "event_time" => $data['event_time'] ?? time(),
                    "action_source" => $data['action_source'] ?? 'website',
                    "event_source_url" => $data['event_source_url'] ?? null,
                    "event_id" => $data['event_id'] ?? null,
                    "user_data" => $data['user_data'] ?? [],
                    "custom_data" => $data['custom_data'] ?? [],
                ]
            ],
            "access_token" => $this->accessToken,
        ];

        $json = json_encode($payload);

        if ($json === false) {
            return [
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
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);

        $response = curl_exec($curl);

        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return [
            "http_code" => $httpCode,
            "curl_errno" => $curlErrno,
            "curl_error" => $curlError,
            "payload" => json_decode($json, true),
            "response" => json_decode($response ?: '', true),
        ];
    }
}
