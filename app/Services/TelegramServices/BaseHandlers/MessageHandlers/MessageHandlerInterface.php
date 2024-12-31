<?php

namespace App\Services\TelegramServices\BaseHandlers\MessageHandlers;

interface MessageHandlerInterface
{
    public function handle($bot, $telegram, $message, $botUser);
}
