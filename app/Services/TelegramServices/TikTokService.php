<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\TikTokHandlers\TextMessageHandler;

class TikTokService extends BaseService
{
    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['text'] = app(TextMessageHandler::class);

        return $messageHandlers;
    }
}
