<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\AudioConversionService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;

/**
 * A voice message or an audio file: its words wait in the diary for the
 * calculation of the meal draft (MealDraftChat), or, right after "🏃 Движение",
 * are kept at once as movement (MovementChat). Transcribing it calls a model and
 * counts for the spam guard.
 */
class AudioMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    protected AudioConversionService $audioConversionService;

    protected MealDraftChat $mealDraftChat;

    protected MovementChat $movementChat;

    public function __construct(AudioConversionService $audioConversionService, MealDraftChat $mealDraftChat, MovementChat $movementChat)
    {
        $this->audioConversionService = $audioConversionService;
        $this->mealDraftChat = $mealDraftChat;
        $this->movementChat = $movementChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        if (! $this->mealDraftChat->allowed($telegram, $botUser, $chatId, 'voice_rate_limited')) {
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

        if (MovementChat::waiting((int) $commonData['userId'])
            && $this->movementChat->say($telegram, $botUser, $chatId, (int) $commonData['userId'], $text)) {
            return;
        }

        $this->mealDraftChat->collect($bot, $telegram, $botUser, $chatId, $commonData['userId'], 'voice', $text);
    }
}
