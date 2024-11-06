<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        if (isset($parts[1])) {
            $productId = $parts[1];

            // Получаем chat_id и message_id
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();

            // Удаляем сообщение с продуктом
            try {
                $telegram->deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            } catch (\Exception $e) {
                Log::error("Error deleting product message: " . $e->getMessage());
            }

            // Удаляем продукт из кеша
            $userId = $callbackQuery->getFrom()->getId();

            $products = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {
                unset($products[$productId]);

                if (count($products) > 0) {
                    // Обновляем кеш
                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));
                } else {
                    // Продуктов больше нет
                    Cache::forget("user_products_{$userId}");

                    // Удаляем сообщение с кнопками «Сохранить» и «Отменить»
                    $finalMessageId = Cache::get("user_final_message_id_{$userId}");

                    if ($finalMessageId) {
                        try {
                            $telegram->deleteMessage([
                                'chat_id' => $chatId,
                                'message_id' => $finalMessageId,
                            ]);
                        } catch (\Exception $e) {
                            Log::error("Error deleting final action message: " . $e->getMessage());
                        }

                        // Удаляем message_id из кеша
                        Cache::forget("user_final_message_id_{$userId}");
                    }
                }

                // Отправляем уведомление пользователю
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Продукт удалён из списка.',
                    'show_alert' => false,
                ]);
            }
        }
    }
}
