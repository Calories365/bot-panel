<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class DiaryApiService
{
    private Client $client;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = 'http://nginx/api/caloriesEndPoint';
        $this->client = new Client();
    }

    public function sendText(string $text)
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Host' => 'calories365.loc',
                ],
                'json' => [
                    'text' => $text,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Error sending text to diary service: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
