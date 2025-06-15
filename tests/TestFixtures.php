<?php

namespace Tests;

trait TestFixtures
{
    /**
     * Load fixture data from JSON file
     */
    protected function loadFixture(string $path): array
    {
        $fullPath = base_path('tests/Fixtures/'.$path);

        if (! file_exists($fullPath)) {
            throw new \Exception("Fixture file not found: {$fullPath}");
        }

        $content = file_get_contents($fullPath);

        return json_decode($content, true);
    }

    /**
     * Get OpenAI Whisper success response
     */
    protected function getWhisperSuccessResponse(): array
    {
        return $this->loadFixture('OpenAI/whisper_success_english.json');
    }

    /**
     * Get Whisper voice transcription response
     */
    protected function getWhisperVoiceResponse(): array
    {
        return $this->loadFixture('OpenAI/whisper_voice_response.json');
    }

    /**
     * Get GPT food analysis success response
     */
    protected function getGptFoodAnalysisResponse(): array
    {
        return $this->loadFixture('OpenAI/gpt_food_analysis_english.json');
    }

    /**
     * Get GPT no products found response
     */
    protected function getGptNoProductsResponse(): array
    {
        return $this->loadFixture('OpenAI/gpt_no_products_found.json');
    }

    /**
     * Get GPT product generation response
     */
    protected function getGptGenerateProductResponse(): array
    {
        return $this->loadFixture('OpenAI/gpt_generate_product_data.json');
    }

    /**
     * Get Diary API products found response
     */
    protected function getDiaryProductsFoundResponse(): array
    {
        return $this->loadFixture('DiaryAPI/products_found_success.json');
    }

    /**
     * Get Diary API products not found response
     */
    protected function getDiaryProductsNotFoundResponse(): array
    {
        return $this->loadFixture('DiaryAPI/products_not_found.json');
    }

    /**
     * Get Diary API save product success response
     */
    protected function getDiarySaveProductResponse(): array
    {
        return $this->loadFixture('DiaryAPI/save_product_success.json');
    }

    /**
     * Get OpenAI API error response
     */
    protected function getOpenAIErrorResponse(): array
    {
        return $this->loadFixture('OpenAI/api_error_response.json');
    }

    /**
     * Get Diary API error response
     */
    protected function getDiaryApiErrorResponse(): array
    {
        return $this->loadFixture('DiaryAPI/api_error_response.json');
    }

    /**
     * Get Telegram voice message webhook payload
     */
    protected function getTelegramVoiceWebhook(): array
    {
        return $this->loadFixture('Telegram/voice_message_webhook.json');
    }

    /**
     * Get Telegram getFile API response
     */
    protected function getTelegramGetFileResponse(): array
    {
        return $this->loadFixture('Telegram/get_file_response.json');
    }
}
