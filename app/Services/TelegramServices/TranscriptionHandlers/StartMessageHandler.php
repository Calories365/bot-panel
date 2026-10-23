<?php

namespace App\Services\TelegramServices\TranscriptionHandlers;

use App\Models\BotAccess;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\StartMessageHandler as BaseStartMessageHandler;

/**
 * The greeting of the bot, then the password for one who has not sent it yet.
 */
class StartMessageHandler extends BaseStartMessageHandler
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        parent::handle($bot, $telegram, $message, $botUser);

        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => BotAccess::granted($bot->id, $message->getFrom()->getId())
                ? __('transcription-bot.send_voice')
                : __('transcription-bot.enter_password'),
        ]);
    }
}
