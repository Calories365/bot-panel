<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EditCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
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
                            ['text' => __('calories365-bot.save'),       'callback_data' => 'editing_save'],
                            ['text' => __('calories365-bot.skip_step'),  'callback_data' => 'editing_skip'],
                            ['text' => __('calories365-bot.cancel'),     'callback_data' => 'editing_cancel'],
                        ]
                    ]
                ]);

                $sentMessage = $telegram->sendMessage([
                    'chat_id'      => $chatId,
                    'text'         => __(
                            'calories365-bot.you_are_editing_product',
                            ['productName' => $productData['product_translation']['name']]
                        )
                        . "\n\n"
                        . __('calories365-bot.please_enter_new_product_name'),
                    'reply_markup' => $replyMarkup,
                ]);

                Cache::put("user_editing_{$userId}", [
                    'product_id'      => $productId,
                    'step'            => 'awaiting_name',
                    'message_id'      => $sentMessage->getMessageId(),
                    'original_product'=> $productData,
                ], now()->addMinutes(30));

                Cache::put("command_block{$userId}", 1, now()->addMinutes(30));

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                ]);
            } else {
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text'             => __('calories365-bot.product_not_found'),
                    'show_alert'       => true,
                ]);
            }
        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'             => __('calories365-bot.invalid_request'),
                'show_alert'       => true,
            ]);
        }
    }
}
