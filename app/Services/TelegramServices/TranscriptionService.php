<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Models\BotUser;
use App\Services\TelegramServices\TranscriptionHandlers\HintMessageHandler;
use App\Services\TelegramServices\TranscriptionHandlers\PasswordGate;
use App\Services\TelegramServices\TranscriptionHandlers\StartMessageHandler;
use App\Services\TelegramServices\TranscriptionHandlers\VoiceMessageHandler;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

/**
 * A bot that answers a voice message with its text, for those who sent its
 * password.
 */
class TranscriptionService extends BaseService
{
    public function __construct(protected PasswordGate $passwordGate)
    {
        parent::__construct();
    }

    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['voice'] = fn () => app(VoiceMessageHandler::class);

        return $messageHandlers;
    }

    protected function getTextMessageHandlers(): array
    {
        $textMessageHandlers = parent::getTextMessageHandlers();

        $textMessageHandlers['/start'] = fn () => app(StartMessageHandler::class);
        $textMessageHandlers['default'] = fn () => app(HintMessageHandler::class);

        return $textMessageHandlers;
    }

    /**
     * The bot has no users of its own with a chosen language: it answers in
     * the language of the user's Telegram.
     */
    public function handle(Bot $bot, Api $telegram, Update $update, ?BotUser $botUser): void
    {
        $message = $update->getMessage();

        app()->setLocale(match (data_get($message, 'from.language_code')) {
            'uk' => 'ua',
            'ru' => 'ru',
            default => 'en',
        });

        if ($message instanceof Message && $update->detectType() === 'message'
            && ! $this->passwordGate->passes($bot, $telegram, $message)) {
            return;
        }

        parent::handle($bot, $telegram, $update, $botUser);
    }
}
