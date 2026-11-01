<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\OnecBatch;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;

/**
 * The buttons of the service message: onec_close_{batch} closes the question
 * or follow-up at once, onec_cancel_{batch} cancels it.
 */
class ButtonCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    public function __construct(protected QuestionCollector $collector) {}

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $parts = explode('_', (string) $callbackQuery->getData());
        $chatId = (int) $callbackQuery->getMessage()->getChat()->getId();

        // Telegram keeps the button spinning until the answer, and closing
        // waits for the model.
        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

        $batch = OnecBatch::whereKey((int) ($parts[2] ?? 0))
            ->whereHas('question', fn ($query) => $query->where('bot_id', $bot->id)->where('chat_id', $chatId))
            ->first();

        if ($batch === null) {
            return;
        }

        match ($parts[1] ?? null) {
            'close' => $this->collector->close($telegram, $batch),
            'cancel' => $this->collector->cancel($telegram, $batch),
            default => null,
        };
    }
}
