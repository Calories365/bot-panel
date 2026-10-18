<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

/**
 * Buttons of the messages the bot sent before the meal draft, one per product
 * (save, cancel, edit, destroy, search, editing_*): they say the message is old
 * instead of spinning.
 */
class OutdatedCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => __('calories365-bot.outdated_message'),
            'show_alert' => true,
        ]);
    }
}
