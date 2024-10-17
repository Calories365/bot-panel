<?php

namespace App\Services\APIService;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class DiaryApiService
{
    private Client $client;
    private string $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = 'https://calories-working.test/api/caloriesEndPoint';
    }

    public function sendText(string $text)
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'json' => [
                    'text' => $text
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Error sending text to diary service: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

}
