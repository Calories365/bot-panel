<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\MealDraftChat;
use App\Services\TelegramServices\CaloriesHandlers\MovementChat;

/**
 * Any text that is not a command or a menu button: about a meal or a
 * correction of the meal draft, "съел яблоко", "убери хлеб", which waits for the
 * calculation, or a short edit, "2 250", which changes the draft at once
 * (MealDraftChat). Neither calls the model now, so neither counts for the spam
 * guard; the calculation does. Right after "🏃 Движение" a text is movement,
 * read by the model at once (MovementChat), and counts.
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

    protected MovementChat $movementChat;

    public function __construct(MealDraftChat $mealDraftChat, MovementChat $movementChat)
    {
        $this->mealDraftChat = $mealDraftChat;
        $this->movementChat = $movementChat;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = trim((string) $message->getText());
        $chatId = $message->getChat()->getId();

        if ($text === '') {
            return;
        }

        // A command the bot does not have: silence looks like a broken bot.
        if (str_starts_with($text, '/')) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('calories365-bot.draft_unsupported')]);

            return;
        }

        $userId = $message->getFrom()->getId();

        if (MovementChat::waiting($userId)) {
            if (! $this->mealDraftChat->allowed($telegram, $botUser, $chatId)
                || $this->movementChat->say($telegram, $botUser, $chatId, $userId, mb_substr($text, 0, 2000))) {
                return;
            }
        }

        if (preg_match(self::SHORT_EDIT, $text)) {
            $this->mealDraftChat->input($bot, $telegram, $botUser, $chatId, $userId, $text);

            return;
        }

        $this->mealDraftChat->collect($bot, $telegram, $botUser, $chatId, $userId, 'text', mb_substr($text, 0, 2000));
    }
}
