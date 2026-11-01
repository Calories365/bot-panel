<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * A sticker, a round video, a location and the like: nothing to give the
 * worker, and the bot says so instead of keeping silent.
 */
class UnsupportedMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => OnecTexts::get('unsupported'),
            'reply_parameters' => json_encode([
                'message_id' => $message->getMessageId(),
                'allow_sending_without_reply' => true,
            ]),
        ]);
    }
}
