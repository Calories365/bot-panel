<?php

namespace App\Services\TelegramServices;

use App\Services\TelegramServices\ApprovalHandlerParts\ContactMessageHandler;
use App\Services\TelegramServices\ApprovalHandlerParts\TextMessageHandler;

class ApprovalService extends BaseService
{
    public function handle($bot, $telegram, $update): void
    {
        self::getUpdateType($bot, $telegram, $update);
    }

    /**
     * @throws \Exception
     */
    public static function handleMessage($bot, $telegram, $update): void
    {
        self::getMessageType($bot, $telegram, $update);
    }

    public static function handleContactMessage($bot, $telegram, $update): void
    {
        ContactMessageHandler::handle($bot, $telegram, $update);
    }

    public static function handleTextMessage($bot, $telegram, $update): void
    {
        TextMessageHandler::handleTextMessage($bot, $telegram, $update);
    }
}
