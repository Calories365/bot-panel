<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;

class ChatGPTService
{
    private Client $client;
    private string $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('OPENAI_API_KEY');
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
        $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Анализируй текст: \"$text\". Выведи только список продуктов с указанием количества в граммах. Если количество не указано, используй среднестатистический вес или порцию. Формат вывода должен соответствовать следующему примеру, где после каждого продукта стоит точка с запятой:

Пример:
Картошка - 100 грамм;
Помидор - 120 грамм;

Если в тексте нет продуктов, выведи: 'продуктов нет'.

Важно:
- Все количества должны быть в граммах.
- После каждого продукта обязательно ставь точку с запятой - это очень важно, если ты этого не сделаешь, мое приложение будет плохо работать, клинты не буду покупать подписку и я умру с голода!
- Не добавляй никакой дополнительной информации кроме списка продуктов.

Пример входного текста: 'Я съел 100 грамм картошки и помидор.'

Ожидаемый вывод:
Картошка - 100 грамм;
Помидор - 120 грамм;"

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
