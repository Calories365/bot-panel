<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\Request2Handlers\TextMessageHandler;

class Request2Service extends BaseService
{
    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['text'] = new TextMessageHandler();

        return $messageHandlers;
    }
}
