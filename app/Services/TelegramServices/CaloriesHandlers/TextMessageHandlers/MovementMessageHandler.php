<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\MovementChat;

/**
 * /move and the keyboard button: what did you do, with the kinds as buttons
 * (MovementChat).
 */
class MovementMessageHandler implements MessageHandlerInterface
{
    protected MovementChat $movementChat;

    public function __construct(MovementChat $movementChat)
    {
        $this->movementChat = $movementChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $this->movementChat->ask($telegram, $botUser, $message->getChat()->getId(), $message->getFrom()->getId());
    }
}
