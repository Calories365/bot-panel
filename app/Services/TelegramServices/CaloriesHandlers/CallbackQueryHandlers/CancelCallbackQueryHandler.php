<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CancelCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public function handle($bot, $telegram, $callbackQuery)
    {
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        // Получаем список продуктов из кеша
        $userProducts = Cache::get("user_products_{$userId}");

        if ($userProducts && is_array($userProducts)) {
            // Удаляем сообщения с продуктами
            foreach ($userProducts as $productId => $productData) {
                if (isset($productData['message_id'])) {
                    try {
                        $telegram->deleteMessage([
                            'chat_id' => $chatId,
                            'message_id' => $productData['message_id'],
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Error deleting message: " . $e->getMessage());
                    }
                }
            }

            // Удаляем сообщение с общими действиями (Сохранить/Отменить)
            $finalMessageId = $callbackQuery->getMessage()->getMessageId();
            try {
                $telegram->deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $finalMessageId,
                ]);
            } catch (\Exception $e) {
                Log::error("Error deleting final action message: " . $e->getMessage());
            }

            // Очищаем кеш пользователя
            Cache::forget("user_products_{$userId}");

            // Уведомляем пользователя об отмене
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Действие отменено. Ваш список продуктов был очищен.',
            ]);

            // Отвечаем на callback_query, чтобы убрать "часики" у пользователя
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Отмена выполнена',
                'show_alert' => false,
            ]);
        } else {
            // Если список продуктов отсутствует
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Ваш список продуктов пуст или уже был очищен.',
            ]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Список уже пуст',
                'show_alert' => false,
            ]);
        }
    }
}
