<?php

namespace App\Interfaces;

interface BotHandlerStrategy
{
    public static function handleMessage($bot, $telegram, $update);

    public static function handleMyChatMember($bot, $telegram, $update);
}
