<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\CaloriesHandlers\InsulinChat;

/**
 * The buttons of the insulin chart: insulin_day_{Y-m-d} shows a day,
 * insulin_lifestyle_{Y-m-d} asks the way of life, insulin_set_{1-5}_{Y-m-d} keeps
 * it and shows the day again.
 */
class InsulinCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    protected InsulinChat $insulinChat;

    public function __construct(InsulinChat $insulinChat)
    {
        $this->insulinChat = $insulinChat;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $parts = explode('_', (string) $callbackQuery->getData());
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $date = $this->date(end($parts));

        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

        match ($parts[1] ?? null) {
            'lifestyle' => $this->insulinChat->askLifestyle($telegram, $botUser, $chatId, $date),
            'set' => $this->set($bot, $telegram, $botUser, $chatId, (int) ($parts[2] ?? 0), $date),
            default => $this->insulinChat->send($bot, $telegram, $botUser, $chatId, $date),
        };
    }

    private function set($bot, $telegram, $botUser, int $chatId, int $level, string $date): void
    {
        if ($level < 1 || $level > 5 || ! $this->insulinChat->setLifestyle($botUser, $level, $date)) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_failed')]);

            return;
        }

        $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.lifestyle_saved', ['lifestyle' => __('calories365-bot.lifestyle_'.$level)])]);
        $this->insulinChat->send($bot, $telegram, $botUser, $chatId, $date);
    }

    /**
     * The day of the button, never one after today.
     */
    private function date(string $part): string
    {
        $today = date('Y-m-d');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $part) && $part <= $today ? $part : $today;
    }
}
