<?php

namespace App\Services\TelegramServices;


use App\Services\TelegramServices\Request2HandlerService\TextMessageHandler;

class Request2Service extends BaseService
{
    public function handle($bot, $telegram, $update)
    {
        self::getUpdateType($bot, $telegram, $update);
    }

    /**
     * @throws \Exception
     */
    public static function handleMessage($bot, $telegram, $update)
    {
        self::getMessageType($bot, $telegram, $update);
    }


    public static function handleTextMessage($bot, $telegram, $update)
    {
        TextMessageHandler::handle($bot, $telegram, $update);
    }
}
