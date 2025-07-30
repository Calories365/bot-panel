<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\ApprovalHandlers\ContactMessageHandler;
use App\Services\TelegramServices\ApprovalHandlers\TextMessageHandler;

/**
 * Class ApprovalService
 *
 * This service implements BaseService
 * strategy for approval bot
 */
class ApprovalService extends BaseService
{
    /**
     * ApprovalService getMessageHandlers.
     *
     * The parent method getMessageHandlers is called, which loads the default handlers
     * and overrides the handlers for the given strategy
     */
    protected function getMessageHandlers(): array
    {
        $messageHandlers = parent::getMessageHandlers();

        $messageHandlers['text'] = fn () => app(TextMessageHandler::class);
        $messageHandlers['contact'] = fn () => app(ContactMessageHandler::class);

        return $messageHandlers;
    }
}
