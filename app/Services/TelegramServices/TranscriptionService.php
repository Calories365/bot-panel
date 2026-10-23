<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Models\BotUser;
use App\Services\TelegramServices\TranscriptionHandlers\HintMessageHandler;
use App\Services\TelegramServices\TranscriptionHandlers\VoiceMessageHandler;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/**
 * A bot that answers a voice message with its text.
 */
class TranscriptionService extends BaseService
{
    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['voice'] = fn () => app(VoiceMessageHandler::class);

        return $messageHandlers;
    }

    protected function getTextMessageHandlers(): array
    {
        $textMessageHandlers = parent::getTextMessageHandlers();

        $textMessageHandlers['default'] = fn () => app(HintMessageHandler::class);

        return $textMessageHandlers;
    }

    /**
     * The bot has no users of its own with a chosen language: it answers in
     * the language of the user's Telegram.
     */
    public function handle(Bot $bot, Api $telegram, Update $update, ?BotUser $botUser): void
    {
        app()->setLocale(match (data_get($update->getMessage(), 'from.language_code')) {
            'uk' => 'ua',
            'ru' => 'ru',
            default => 'en',
        });

        parent::handle($bot, $telegram, $update, $botUser);
    }
}
