<?php

namespace franciscoblancojn\LaravelTools;


class LaravelToolsGoogle
{
    private string $measurementId;
    private string $apiSecret;

    public function __construct($config = [])
    {
        $this->measurementId = isset($config['measurementId']) ? $config['measurementId'] : null;
        $this->apiSecret = isset($config['apiSecret']) ? $config['apiSecret'] : null;
    }

    function sendAction($data = [
        "client_id" => "",
        "event_name" => "",
        "params" => [],
    ])
    {
        $measurementId = $this->measurementId;
        $apiSecret = $this->apiSecret;

        $url = "https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}";

        $data = [
            "client_id" => $data['client_id'],
            "events" => [
                [
                    "name" => $data['event_name'],
                    "params" => $data['params'],
                ]
            ]
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return json_encode($response);
    }
}
