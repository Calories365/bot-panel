<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use App\Models\BotUser;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

interface BotHandlerStrategy
{
    /**
     * Handle incoming Telegram update
     */
    public function handle(Bot $bot, Api $telegram, Update $update, ?BotUser $botUser): void;

    /**
     * Get list of commands that should be excluded from middleware processing
     */
    public function getExcludedCommands(): array;
}
