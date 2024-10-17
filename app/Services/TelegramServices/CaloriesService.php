<?php

namespace App\Services\TelegramServices;


use App\Services\TelegramServices\CaloriesHandlerParts\AudioMessageHandler;

class CaloriesService extends BaseService
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

    public static function handleAudioMessage($bot, $telegram, $update): void
    {
        AudioMessageHandler::handle($bot, $telegram, $update);
    }
}
