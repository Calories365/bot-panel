<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\CaloriesHandlers\MovementChat;

/**
 * The buttons of movement: move_ask asks what the user did, move_more shows
 * all the kinds, move_type_{type} asks how long, move_add_{type}_{minutes}
 * keeps it, move_del_{id} removes a kept one. A kind may hold "_", walk_brisk.
 */
class MovementCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    protected MovementChat $movementChat;

    public function __construct(MovementChat $movementChat)
    {
        $this->movementChat = $movementChat;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $data = (string) $callbackQuery->getData();
        $message = $callbackQuery->getMessage();
        $chatId = $message->getChat()->getId();
        $userId = $callbackQuery->getFrom()->getId();

        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

        match (true) {
            $data === 'move_ask' => $this->movementChat->ask($telegram, $botUser, $chatId, $userId),
            $data === 'move_more' => $this->movementChat->ask($telegram, $botUser, $chatId, $userId, true),
            (bool) preg_match('/^move_type_([a-z_]+)$/', $data, $match) => $this->movementChat->askMinutes($telegram, $botUser, $chatId, $match[1]),
            (bool) preg_match('/^move_add_([a-z_]+)_(\d{1,3})$/', $data, $match) => $this->movementChat->add($telegram, $botUser, $chatId, $userId, $match[1], (int) $match[2]),
            (bool) preg_match('/^move_del_(\d+)$/', $data, $match) => $this->movementChat->remove($telegram, $botUser, $chatId, $message->getMessageId(), (int) $match[1]),
            default => null,
        };
    }
}
