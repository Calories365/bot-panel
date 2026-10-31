<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\InsulinChat;

/**
 * /insulin and the keyboard button: the insulin chart of today.
 */
class InsulinMessageHandler implements MessageHandlerInterface
{
    protected InsulinChat $insulinChat;

    public function __construct(InsulinChat $insulinChat)
    {
        $this->insulinChat = $insulinChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $this->insulinChat->send($bot, $telegram, $botUser, $message->getChat()->getId());
    }
}
