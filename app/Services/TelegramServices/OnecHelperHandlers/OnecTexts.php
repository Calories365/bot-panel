<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

/**
 * The texts of the 1C helper bot, lang/ru/onec-helper-bot.php. The bot is
 * called from webhooks, queued jobs and the worker's API alike, so the locale
 * is given here and not taken from the request.
 */
class OnecTexts
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        return (string) __('onec-helper-bot.'.$key, $replace, 'ru');
    }

    public static function messages(int $count): string
    {
        return trans_choice('onec-helper-bot.messages', $count, [], 'ru');
    }

    public static function screens(int $count): string
    {
        return trans_choice('onec-helper-bot.screens', $count, [], 'ru');
    }
}
