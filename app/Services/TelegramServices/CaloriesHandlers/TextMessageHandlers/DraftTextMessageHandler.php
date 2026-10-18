<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\MealDraftChat;

/**
 * Any text that is not a command or a menu button: about a meal or a
 * correction of the meal draft, "съел яблоко", "убери хлеб", which waits for the
 * calculation, or a short edit, "2 250", which changes the draft at once
 * (MealDraftChat). Neither calls the model now, so neither counts for the spam
 * guard; the calculation does.
 */
class DraftTextMessageHandler implements MessageHandlerInterface
{
    public bool $blockAble = true;

    /**
     * A short edit of the draft, "2 250" or "убрать 3", which the diary applies
     * without its model.
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

        $userId = $message->getFrom()->getId();

        if (preg_match(self::SHORT_EDIT, $text)) {
            $this->mealDraftChat->input($bot, $telegram, $botUser, $chatId, $userId, $text);

            return;
        }

        $this->mealDraftChat->collect($bot, $telegram, $botUser, $chatId, $userId, 'text', mb_substr($text, 0, 2000));
    }
}
