<?php

namespace App\Services\ChatGPTServices;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpeechToTextService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client;
    }

    /**
     * Определяем нужный ключ в зависимости от текущей локали
     */
    private function getApiKey(): string
    {
        $locale = app()->getLocale();

        switch ($locale) {
            case 'ua':
                return env('OPENAI_API_KEY_UK');

            case 'en':
                return env('OPENAI_API_KEY_EN');

            default:
                return env('OPENAI_API_KEY_RU');
        }
    }

    /* ---------- convertSpeechToText ---------- */
    public function convertSpeechToText(string $filePath)
    {
        $locale = app()->getLocale();
        $languageCode = match ($locale) {
            'ua' => 'uk',
            'ru' => 'ru',
            'en' => 'en',
            default => 'uk',
        };

        $response = Http::timeout(45)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->attach('model', 'whisper-1')
            ->attach('language', $languageCode)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->getApiKey(),
            ])
            ->post('https://api.openai.com/v1/audio/transcriptions')
            ->throw();

        $data = $response->json();
        Log::info('data');
        Log::info($data);
        $res = isset($data['text']);
        Log::info($res);
        return isset($data['text'])
            ? $this->analyzeFoodIntake($data['text'])
            : false;
    }

    /* ---------- analyzeFoodIntake ---------- */
    public function analyzeFoodIntake(string $text)
    {
        $prompt = __('calories365-bot.prompt_analyze_food_intake', [
            'text' => $text,
        ]);
        Log::info('$prompt: ');
        Log::info($prompt);
        try {
            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getApiKey(),
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            Log::info('Res: ');
            Log::info(print_r($result, true));
            return $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /* ---------- generateNewProductData ---------- */
    public function generateNewProductData(string $text)
    {
        $prompt = __('calories365-bot.prompt_generate_new_product_data', [
            'text' => $text,
        ]);

        try {
            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getApiKey(),
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            return $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');
        } catch (\Throwable $e) {
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
                        return $detail['id'].' - '.$detail['name'];
                    }, $details);

                    $prompt .= __('calories365-bot.prompt_choose_relevant_products_part', [
                        'name' => $name,
                        'productNames' => implode(', ', $productNames),
                    ]).' ';
                }

                $prompt .= __('calories365-bot.prompt_choose_relevant_products_footer');

                $chosenProductName .= $this->askGPTForRelevance($prompt);
            }

        } catch (\Exception $e) {
            Log::error('Error in choosing product: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }

        return true;
    }

    /* ---------- askGPTForRelevance ---------- */
    private function askGPTForRelevance(string $prompt)
    {
        try {
            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getApiKey(),
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            return $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
