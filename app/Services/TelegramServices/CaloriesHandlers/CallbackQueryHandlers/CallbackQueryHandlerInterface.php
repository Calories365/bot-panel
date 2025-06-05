<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

interface CallbackQueryHandlerInterface
{
    public function handle($bot, $telegram, $callbackQuery, $botUser);
}
