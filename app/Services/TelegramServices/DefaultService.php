<?php

namespace App\Services\TelegramServices;


class DefaultService extends BaseService
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

}
