<?php

namespace App\Services\TelegramServices\BaseHandlers\UpdateHandlers;

interface UpdateHandlerInterface
{
    public function handle($bot, $telegram, $update, $botUser);
}
