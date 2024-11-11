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
        Log::info('promt: ' . $text);
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
                        'content' => "Анализируй текст: \"$text\". Выведи только список продуктов с указанием количества в граммах. Если количество не указано, используй среднестатистический вес или порцию. Формат вывода должен строго соответствовать следующему примеру, где после каждого продукта стоит точка с запятой:

Пример:
Картошка - 100 грамм;
Помидор - 120 грамм;
Курица221 - 200 грамм;

Если в тексте нет продуктов, выведи: 'продуктов нет'.

Важно:
- Все количества должны быть в граммах.
- После каждого продукта обязательно ставь точку с запятой.
- Не добавляй никакой дополнительной информации кроме списка продуктов.
- Продукт может содержать буквы и цифры (например, курица221 или курица два два один). Сохраняй полные названия продуктов без изменений.
- Убедись, что каждый продукт и его количество разделены тире и пробелами, как в примере.
- Не изменяй исходное название продукта, даже если оно содержит цифры или нестандартные символы.

Примеры входного текста и ожидаемого вывода:

1. Входной текст: 'Я съел 100 грамм картошки и помидор.'
   Ожидаемый вывод:
   Картошка - 100 грамм;
   Помидор - 120 грамм;

2. Входной текст: 'Я съел 100 грамм картошки, помидор и курица два два один.'
   Ожидаемый вывод:
   Картошка - 100 грамм;
   Помидор - 120 грамм;
   Курица221 - 200 грамм;

3. Входной текст: 'Я съел вареный картофель'
   Ожидаемый вывод:
   Вареный картофель - 200 грамм;

4. Входной текст: 'Сегодня ничего не ел.'
   Ожидаемый вывод:
   продуктов нет
"


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
