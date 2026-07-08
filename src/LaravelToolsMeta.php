<?php

namespace franciscoblancojn\LaravelTools;


class LaravelToolsMeta
{
    private string $pixelId;
    private string $accessToken;

    public function __construct($config = [])
    {
        $this->pixelId = isset($config['pixelId']) ? $config['pixelId'] : null;
        $this->accessToken = isset($config['accessToken']) ? $config['accessToken'] : null;
    }

    function sendAction($data = [
        "event_name" => "",
        "action_source" => "website",
        "user_data" => [],
        "custom_data" => [],
    ])
    {
        $pixelId = $this->pixelId;
        $accessToken = $this->accessToken;

        $url = "https://graph.facebook.com/v23.0/{$pixelId}/events?access_token={$accessToken}";

        $data = [
            "data" => [
                [
                    "event_name" => $data['event_name'],
                    "event_time" => time(),
                    "action_source" => $data['action_source'],

                    "user_data" => $data['user_data'],

                    "custom_data" => $data['custom_data'],

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
