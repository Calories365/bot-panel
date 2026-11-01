<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;

/**
 * /start and any other command: what to send to the bot. A command does not
 * go into a question.
 */
class StartMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser)
    {
        $telegram->sendMessage([
            'chat_id' => $message->getChat()->getId(),
            'text' => OnecTexts::get('start'),
        ]);
    }
}
