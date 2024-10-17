<?php

namespace App\Services\ChatGPTServices;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class SpeechToTextService
{
    private Client $client;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->client = new Client();
        $this->apiKey = $apiKey;
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
//        Log::info('start analyze');
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
                        'content' => "Анализ текста: \"$text\". Укажи список продуктов с количеством. Если количество не указано, используйте среднестатистический вес или порцию.
                        надо именно список продуктов и все, например текст: я сьел 100грамм каротошки и помилрк. Ты должен будешь на такой ответ вывеси список:
                        Картошка - 100грамм;
                        Помидор - 120грамм;
                        то есть без лишней информации
                        и надо все переводить в граммы, если не указано их количество, если например говориться что 3 яйца, ты должен будешь написать именно среднестатистическое количество грамм в 3 яйцах
                        если текст не содержит продуктов для списка - пиши: 'продуктов нет'"
                    ]
                ]
            ]
        ]);

        try {
            $result = json_decode($response->getBody()->getContents(), true);
//            Log::info($result);

            $output = $result['choices'][0]['message']['content'] ?? 'Не удалось извлечь данные.';
            return $output;
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function chooseTheMostRelevantProduct(array $products)
    {
        try {

            $chosenProductName = '';

            for ($i = 0; $i < count($products); $i += 5) {
                $prompt = '';

                for ($j = $i; $j < $i + 5 && $j < count($products); $j++) {
                    $name = $products[$j]['name'];
                    $details = $products[$j]['details'];

                    $productNames = array_map(function ($detail) {
                        return $detail['id'] . ' - ' . $detail['name'];
                    }, $details);

                    $prompt .= "Какой продукт наиболее соответствует названию \"$name\"? Вот доступные варианты: " . implode(', ', $productNames) . '. ';
                }

                $prompt .= 'верни ответ в следующем формате,
                название продукта1 - id;
                название продукта2 - id;
                если подходящуго продукта нет, то ответ должен быть в формете
                названик продукта - (его калораж на 100грамм, белки, жири, углеводы);
                ';

                $chosenProductName .= $this->askGPTForRelevance($prompt);
            }

//            Log::info("Chosen products: " . $chosenProductName);

        } catch (\Exception $e) {
            Log::error("Error in choosing product: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
        return true;
    }

    private function askGPTForRelevance($prompt)
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
                        'content' => $prompt
                    ]
                ]
            ]
        ]);

        try {
            $result = json_decode($response->getBody()->getContents(), true);
//            Log::info($result);

            $output = $result['choices'][0]['message']['content'] ?? 'Не удалось извлечь данные.';
            return $output;
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }


}
