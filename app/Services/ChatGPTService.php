<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class ChatGPTService
{
    private Client $client;
    private string $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $locale = app()->getLocale();
        switch ($locale) {
            case 'ua':
                $this->apiKey = env('OPENAI_API_KEY_UK');
                break;
            case 'en':
                $this->apiKey = env('OPENAI_API_KEY_EN');
                break;
            default:
                $this->apiKey = env('OPENAI_API_KEY_RU');
        }

    }

    public function convertSpeechToText(string $filePath)
    {
        $multipartBody = new MultipartStream([
            [
                'name' => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath)
            ],
            [
                'name' => 'model',
                'contents' => 'whisper-1'
            ]
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'multipart/form-data; boundary=' . $multipartBody->getBoundary()
        ];

        $request = new Request('POST', 'https://api.openai.com/v1/audio/transcriptions', $headers, $multipartBody);

        try {
            $response = $this->client->send($request);
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['text'])) {
                return $this->analyzeFoodIntake($data['text']);
            }
            return false;
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function analyzeFoodIntake(string $text)
    {
        Log::info('promt: ' . $text);
        $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json'
            ],
            'json' => [
                'model'    => 'gpt-4o',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => __("calories365-bot.prompt_analyze_intake_text", [
                            'text' => $text
                        ]),
                    ]
                ]
            ]
        ]);


        try {
            $result = json_decode($response->getBody()->getContents(), true);

            return $result['choices'][0]['message']['content'] ?? 'Не удалось извлечь данные.';
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
