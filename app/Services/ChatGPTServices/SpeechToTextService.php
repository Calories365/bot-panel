<?php

namespace App\Services\ChatGPTServices;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

class SpeechToTextService
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
- Если продукт имеет описательные слова (например, вареный картофель), переставляй описание после названия продукта (например, 'вареный картофель' → 'картофель вареный').
- Убедись, что каждый продукт и его количество разделены тире и пробелами, как в примере.
- Не изменяй исходное название продукта, даже если оно содержит цифры или нестандартные символы.

Примеры входного текста и ожидаемого вывода:

1. Входной текст: 'Я съел 100 грамм картошки и помидор.'
   Ожидаемый вывод:
   Картошка - 100 грамм;
   Помидор - 120 грамм;

2. Входной текст: 'Я съел 100 грамм картошки, помидор и курица221.'
   Ожидаемый вывод:
   Картошка - 100 грамм;
   Помидор - 120 грамм;
   Курица221 - 200 грамм;

3. Входной текст: 'Я съел 100 грамм картошки, помидор и курица два два один.'
   Ожидаемый вывод:
   Картошка - 100 грамм;
   Помидор - 120 грамм;
   Курица два два один - 200 грамм;

4. Входной текст: 'Я съел вареный картофель'
   Ожидаемый вывод:
   Картофель вареный - 200 грамм;

5. Входной текст: 'Сегодня ничего не ел.'
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

    /**
     * @throws GuzzleException
     */
    public function generateNewProductData(string $text)
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
                        'content' => "Есть продукт: \"$text\". Выведи КБЖУ (Калории, Белки, Жиры, Углеводы) на 100 грамм продукта.
                     Формат вывода должен строго соответствовать следующему примеру, где после каждого параметра стоит точка с запятой:

Пример: Калории - 890; Белки - 0.2; Жиры - 100; Углеводы - 0;

Важно:

Все значения должны соответствовать 100 граммам продукта.
После каждого параметра обязательно ставь точку с запятой.
Не добавляй никакой дополнительной информации кроме списка КБЖУ.
Название продукта сохраняй без изменений, даже если оно содержит цифры или нестандартные символы.
Убедись, что каждый параметр и его значение разделены тире и пробелами, как в примере.
Так же учти что пользователь может говорить названия продуктов как общие или брендовые, например, 'Halls' или 'Конфета Bob and Snail,' такое тоже надо распознавать и возвращать информацию

Входной текст: Калории - 890; Белки - 0.2; Жиры - 100; Углеводы - 0;

Входной текст: Калории - 52; Белки - 0.3; Жиры - 0.2; Углеводы - 14;

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
