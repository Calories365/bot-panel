<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;

class EditCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        if (isset($parts[1])) {
            $productId = $parts[1];

            $userId = $callbackQuery->getFrom()->getId();
            $chatId = $callbackQuery->getMessage()->getChat()->getId();

            // Получаем список продуктов из кеша
            $userProducts = Cache::get("user_products_{$userId}");

            if ($userProducts && isset($userProducts[$productId])) {
                $productData = $userProducts[$productId];

                // Отправляем пользователю сообщение с просьбой ввести новое название продукта
                $sentMessage = $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Вы редактируете продукт: *{$productData['product_translation']['name']}*\n\nПожалуйста, введите новое название продукта или отправьте /skip, чтобы оставить без изменений.",
                    'parse_mode' => 'Markdown',
                ]);

                // Сохраняем состояние редактирования в кеше, включая message_id
                Cache::put("user_editing_{$userId}", [
                    'product_id' => $productId,
                    'step' => 'awaiting_name', // Первый шаг редактирования
                    'message_id' => $sentMessage->getMessageId(),
                    'original_product' => $productData, // Сохраняем исходные данные продукта
                ], now()->addMinutes(30));

                // Отвечаем на callback_query, чтобы убрать "часики"
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                ]);
            } else {
                // Продукт не найден в кеше
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Продукт не найден или истекло время сессии.',
                    'show_alert' => true,
                ]);
            }
        } else {
            // Некорректный формат callback_data
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Некорректный запрос.',
                'show_alert' => true,
            ]);
        }
    }
}
