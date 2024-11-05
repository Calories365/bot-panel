<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;

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
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            // Удаляем продукт из сессии или кеша
            $userId = $callbackQuery->getFrom()->getId();

            $products = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {
                unset($products[$productId]);
                Cache::put("user_products_{$userId}", $products);
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
