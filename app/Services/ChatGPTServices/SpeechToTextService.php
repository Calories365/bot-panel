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
                return env('OPENAI_API_KEY_UA');

            case 'en':
                return env('OPENAI_API_KEY_EN');

            default:
                return env('OPENAI_API_KEY_RU');
        }
    }

    private function useLocalModels(): bool
    {
        return filter_var(config('services.openai.local_models', false), FILTER_VALIDATE_BOOL);
    }

    private function normalizeBaseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: config('services.openai.base_url', 'https://api.openai.com')), '/');
    }

    private function joinBaseAndPath(string $baseUrl, string $path): string
    {
        $base = rtrim($baseUrl, '/');
        $normalizedPath = '/'.ltrim($path, '/');

        // Prevent duplicated /v1 segment when base already ends with /v1.
        if (str_ends_with($base, '/v1') && str_starts_with($normalizedPath, '/v1/')) {
            $normalizedPath = substr($normalizedPath, 3);
        }

        return $base.$normalizedPath;
    }

    private function getEndpoint(string $type): string
    {
        if ($type === 'audio') {
            $baseUrl = $this->useLocalModels()
                ? ($this->normalizeBaseUrl(config('services.openai.proxy_base_url')
                    ?: config('services.openai.local_audio_base_url')))
                : $this->normalizeBaseUrl(config('services.openai.base_url'));

            return $this->joinBaseAndPath(
                $baseUrl,
                config('services.openai.audio_path', '/v1/audio/transcriptions')
            );
        }

        $baseUrl = $this->useLocalModels()
            ? ($this->normalizeBaseUrl(config('services.openai.proxy_base_url')
                ?: config('services.openai.local_chat_base_url')))
            : $this->normalizeBaseUrl(config('services.openai.base_url'));

        return $this->joinBaseAndPath(
            $baseUrl,
            config('services.openai.chat_path', '/v1/chat/completions')
        );
    }

    private function getAuthorizationToken(string $type): string
    {
        if (! $this->useLocalModels()) {
            return $this->getApiKey();
        }

        if ($type === 'audio') {
            return config('services.openai.proxy_token')
                ?: config('services.openai.local_audio_token')
                ?: $this->getApiKey();
        }

        return config('services.openai.proxy_token')
            ?: config('services.openai.local_chat_token')
            ?: $this->getApiKey();
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
            ->attach('model', config('services.openai.audio_model', 'whisper-1'))
            ->attach('language', $languageCode)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->getAuthorizationToken('audio'),
            ])
            ->post($this->getEndpoint('audio'))
            ->throw();

        $data = $response->json();
        $res = isset($data['text']);

        Log::info('-----------------------');
        Log::info('Сконвертированый текст с помощью Whisper: ');
        Log::info(print_r($data['text'], true));
        Log::info('-----------------------');

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
        try {
            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            $final_result = $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');

            Log::info('-----------------------');
            Log::info('Проанализированый текст с помощью gpt-4o: ');
            Log::info(print_r($final_result, true));
            Log::info('-----------------------');

            return $final_result;
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
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            $final_result = $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');

            Log::info('-----------------------');
            Log::info('Сгенерированые данные продукта помощью gpt-4o: ');
            Log::info(print_r($final_result, true));
            Log::info('-----------------------');

            return $final_result;
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
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o'),
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
