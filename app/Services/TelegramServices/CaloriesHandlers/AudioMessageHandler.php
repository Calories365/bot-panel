<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\AudioConversionService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;

/**
 * A voice message: its words wait in the diary for the calculation of the meal
 * draft (MealDraftChat). Transcribing it calls a model and counts for the spam
 * guard.
 */
class AudioMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    protected AudioConversionService $audioConversionService;

    protected MealDraftChat $mealDraftChat;

    public function __construct(AudioConversionService $audioConversionService, MealDraftChat $mealDraftChat)
    {
        $this->audioConversionService = $audioConversionService;
        $this->mealDraftChat = $mealDraftChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        if (! isset($message['voice']) || ! $this->mealDraftChat->allowed($telegram, $botUser, $chatId, 'voice_rate_limited')) {
            return;
        }

        $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message);

        if ($text === null) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.failed_to_recognize_audio_message'),
            ]);

            return;
        }

        $this->mealDraftChat->collect($bot, $telegram, $botUser, $chatId, $commonData['userId'], 'voice', $text);
    }
}
