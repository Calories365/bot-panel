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
        $this->apiUrl = 'http://nginx/api';
        $this->client = new Client();
    }

    public function sendText(string $text)
    {
        try {
            $response = $this->client->post($this->apiUrl . '/caloriesEndPoint', [
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
    public function getTheMostRelevantProduct(string $text)
    {
        try {
            $response = $this->client->post($this->apiUrl . '/caloriesEndPoint/getTheMostRelevantProduct', [
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

    public function saveProduct(array $data)
    {
        try {
            $response = $this->client->post($this->apiUrl . '/caloriesEndPoint/saveProduct', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Host' => 'calories365.loc',
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Error saving product to diary service: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function saveFoodConsumption(array $data)
    {
        try {
            $response = $this->client->post($this->apiUrl . '/caloriesEndPoint/saveFoodConsumption', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Host' => 'calories365.loc',
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Error saving food consumption to diary service: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    public function showUserStats($date)
    {
        try {
            $response = $this->client->get($this->apiUrl . '/caloriesEndPoint/showUserStats/' . $date, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Host' => 'calories365.loc',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error("Error retrieving user stats from diary service: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

}
