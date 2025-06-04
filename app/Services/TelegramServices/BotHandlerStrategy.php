<?php

namespace App\Services\TelegramServices;

interface BotHandlerStrategy
{
    /**
     * Handle incoming Telegram update
     */
    public function handle($bot, $telegram, $update, $botUser): void;

    /**
     * Get list of commands that should be excluded from middleware processing
     */
    public function getExcludedCommands(): array;
} 