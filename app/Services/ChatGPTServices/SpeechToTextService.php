<?php

namespace App\Services\ChatGPTServices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Speech to text through OpenAI, config('services.openai.models.transcription').
 * What the words say about food the diary reads, in the meal draft.
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
     * The words of the recording; false when it has none. The diary's meal
     * draft reads what they say about food. Without the language hint the
     * model detects the language itself: the transcription bot hears any
     * language, whatever the interface of Telegram is.
     *
     * @throws \Illuminate\Http\Client\RequestException when transcription fails
     */
    public function transcribe(string $filePath, bool $withLanguageHint = true): string|false
    {
        $request = Http::timeout(45)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->attach('model', config('services.openai.models.transcription'));

        if ($withLanguageHint) {
            // gpt-transcribe takes a list of expected languages instead of "language".
            $request->attach('languages[]', match (app()->getLocale()) {
                'ua' => 'uk',
                'ru' => 'ru',
                'en' => 'en',
                default => 'uk',
            });
        }

        $response = $request
            ->withToken($this->getApiKey())
            ->post($this->url('/v1/audio/transcriptions'))
            ->throw();

        $data = $response->json();

        Log::info('Transcribed speech', ['text' => $data['text'] ?? null]);

        $text = trim((string) ($data['text'] ?? ''));

        return $text !== '' ? $text : false;
    }
}
