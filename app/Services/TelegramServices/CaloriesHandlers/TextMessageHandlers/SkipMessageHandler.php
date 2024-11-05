<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\MessageHandlers\MessageHandlerInterface;
use Illuminate\Support\Facades\Cache;

class SkipMessageHandler implements MessageHandlerInterface
{
    public function handle($bot, $telegram, $message)
    {
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();

        // Проверяем, находится ли пользователь в процессе редактирования
        $editingState = Cache::get("user_editing_{$userId}");

        if ($editingState) {
            // Пользователь в процессе редактирования
            // Вызываем общий обработчик для редактирования, передавая команду '/skip'
            $editMessageHandler = app(EditMessageHandler::class);
            $editMessageHandler->handleSkip($bot, $telegram, $message, $editingState);
        } else {
            // Пользователь не в процессе редактирования
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Нет действия, которое можно пропустить.',
            ]);
        }
    }
}
