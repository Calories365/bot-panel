<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\RequestHandlers\TextMessageHandler;

class RequestService extends BaseService
{
    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['text'] = app(TextMessageHandler::class);

        return $messageHandlers;
    }
}
