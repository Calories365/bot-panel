<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;
    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        if (isset($parts[1])) {
            $productId = $parts[1];

            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();

            try {
                $telegram->deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            } catch (\Exception $e) {
                Log::error("Error deleting product message: " . $e->getMessage());
            }

            $userId = $callbackQuery->getFrom()->getId();

            $products = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {
                unset($products[$productId]);

                if (count($products) > 0) {
                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));
                } else {
                    Cache::forget("user_products_{$userId}");

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

                        Cache::forget("user_final_message_id_{$userId}");
                    }
                }

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => __('calories365-bot.product_removed_from_list'),
                    'show_alert' => false,
                ]);
            }
        }
    }
}
