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

            $userProducts = Cache::get("user_products_{$userId}");

            if ($userProducts && isset($userProducts[$productId])) {
                $productData = $userProducts[$productId];

                $replyMarkup = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Сохранить', 'callback_data' => 'editing_save'],
                            ['text' => 'Пропустить шаг', 'callback_data' => 'editing_skip'],
                            ['text' => 'Отменить', 'callback_data' => 'editing_cancel'],
                        ]
                    ]
                ]);

                $sentMessage = $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Вы редактируете продукт: *{$productData['product_translation']['name']}*\n\nПожалуйста, введите новое название продукта.",
                    'reply_markup' => $replyMarkup,
                ]);

                Cache::put("user_editing_{$userId}", [
                    'product_id' => $productId,
                    'step' => 'awaiting_name',
                    'message_id' => $sentMessage->getMessageId(),
                    'original_product' => $productData,
                ], now()->addMinutes(30));

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                ]);
            } else {
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Продукт не найден или истекло время сессии.',
                    'show_alert' => true,
                ]);
            }
        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Некорректный запрос.',
                'show_alert' => true,
            ]);
        }
    }
}
