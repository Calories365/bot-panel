<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * A message the bot cannot read, a sticker or a video: silence looks like a broken
 * bot, so it says what it understands.
 */
class UnsupportedMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => __('calories365-bot.draft_unsupported'),
        ]);
    }
}
