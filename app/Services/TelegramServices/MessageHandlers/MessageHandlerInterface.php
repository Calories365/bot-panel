<?php

namespace App\Services\TelegramServices\MessageHandlers;

interface MessageHandlerInterface
{
    public function handle($bot, $telegram, $message);
}
