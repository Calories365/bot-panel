<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EditActionCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        // Получаем состояние редактирования
        $editingState = Cache::get("user_editing_{$userId}");

        if (!$editingState) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Сессия редактирования истекла или отсутствует.',
                'show_alert' => true,
            ]);
            return;
        }

        $productId = $editingState['product_id'];
        $messageId = $editingState['message_id'];

        // Получаем список продуктов
        $userProducts = Cache::get("user_products_{$userId}");

        if (!$userProducts || !isset($userProducts[$productId])) {
            $this->clearEditingState($userId);
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Продукт не найден или истекло время сессии.',
                'show_alert' => true,
            ]);
            return;
        }

        if ($callbackData === 'edit_save') {
            // Сохранить изменения
            $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);

            // Отправляем всплывающее уведомление
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Изменения сохранены.',
                'show_alert' => true,
            ]);
        } elseif ($callbackData === 'edit_cancel') {
            // Отменить изменения
            $this->exitEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);

            // Отправляем всплывающее уведомление
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Редактирование отменено.',
                'show_alert' => true,
            ]);
        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Неизвестная команда.',
                'show_alert' => true,
            ]);
        }
    }

    // Добавьте методы saveEditing, exitEditing и clearEditingState аналогично тем, что были в EditMessageHandler
}
