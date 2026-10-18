<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\MealDraftChat;

/**
 * Any text that is not a command or a menu button: a meal or a correction of
 * the meal draft, "съел яблоко", "2 250", "убери хлеб" (MealDraftChat).
 */
class DraftTextMessageHandler implements MessageHandlerInterface
{
    public bool $blockAble = true;

    /**
     * A short edit of the draft, "2 250" or "убрать 3", which the diary applies
     * without its model: it does not count for the spam guard.
     */
    private const SHORT_EDIT = '/^\s*(?:\d{1,2}\s*[-—:.]?\s*\d{1,4}\s*(?:г|гр|грам\p{L}*|g)?\.?|(?:-|убра\p{L}*|убери|удал\p{L}*|прибра\p{L}*|прибери|видал\p{L}*|remove|delete)\s*\d{1,2})\s*$/iu';

    protected MealDraftChat $mealDraftChat;

    public function __construct(MealDraftChat $mealDraftChat)
    {
        $this->mealDraftChat = $mealDraftChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = trim((string) $message->getText());
        $chatId = $message->getChat()->getId();

        if ($text === '' || str_starts_with($text, '/')) {
            return;
        }

        if (! preg_match(self::SHORT_EDIT, $text) && ! $this->mealDraftChat->allowed($telegram, $botUser, $chatId)) {
            return;
        }

        $this->mealDraftChat->input($telegram, $botUser, $chatId, $message->getFrom()->getId(), mb_substr($text, 0, 2000));
    }
}
