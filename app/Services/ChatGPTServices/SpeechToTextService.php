<?php

namespace App\Services\ChatGPTServices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Speech to a list of products, and nutrition facts of a product, through OpenAI.
 *
 * Models come from config('services.openai.models'): transcription for speech,
 * meal_analysis for the list of products, product for nutrition facts.
 */
class SpeechToTextService
{
    /**
     * Key of the OpenAI project for the current locale.
     */
    private function getApiKey(): string
    {
        $keys = config('services.openai.keys');

        return (string) ($keys[app()->getLocale()] ?? $keys['ru'] ?? '');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.openai.base_url'), '/').$path;
    }

    /**
     * Transcribe the recording and turn the text into "Name - N grams;" lines.
     *
     * @return string|array{error: string}|false false when the transcript has no text
     *
     * @throws \Illuminate\Http\Client\RequestException when transcription fails
     */
    public function convertSpeechToText(string $filePath)
    {
        $languageCode = match (app()->getLocale()) {
            'ua' => 'uk',
            'ru' => 'ru',
            'en' => 'en',
            default => 'uk',
        };

        $response = Http::timeout(45)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->attach('model', config('services.openai.models.transcription'))
            // gpt-transcribe takes a list of expected languages instead of "language".
            ->attach('languages[]', $languageCode)
            ->withToken($this->getApiKey())
            ->post($this->url('/v1/audio/transcriptions'))
            ->throw();

        $data = $response->json();

        Log::info('Transcribed speech', ['text' => $data['text'] ?? null]);

        return isset($data['text'])
            ? $this->analyzeFoodIntake($data['text'])
            : false;
    }

    /**
     * @return string|array{error: string}
     */
    public function analyzeFoodIntake(string $text)
    {
        $prompt = __('calories365-bot.prompt_analyze_food_intake', [
            'text' => $text,
        ]);

        return $this->complete(config('services.openai.models.meal_analysis'), $prompt);
    }

    /**
     * @return string|array{error: string}
     */
    public function generateNewProductData(string $text)
    {
        $prompt = __('calories365-bot.prompt_generate_new_product_data', [
            'text' => $text,
        ]);

        return $this->complete(config('services.openai.models.product'), $prompt);
    }

    /**
     * One chat completion; the reply text, or ['error' => ...] when the call failed.
     *
     * @return string|array{error: string}
     */
    private function complete(string $model, string $prompt)
    {
        try {
            $result = Http::timeout(45)
                ->withToken($this->getApiKey())
                ->post($this->url('/v1/chat/completions'), [
                    'model' => $model,
                    'reasoning_effort' => config('services.openai.reasoning_effort'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])
                ->throw()
                ->json();

            $content = $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');

            Log::info('OpenAI answer', ['model' => $model, 'content' => $content]);

            return $content;
        } catch (\Throwable $e) {
            Log::error('OpenAI request failed', ['model' => $model, 'error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }
}
