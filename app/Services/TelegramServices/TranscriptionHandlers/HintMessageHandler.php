<?php

namespace App\Services\TelegramServices\TranscriptionHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

class HintMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => __('transcription-bot.send_voice'),
        ]);
    }
}
