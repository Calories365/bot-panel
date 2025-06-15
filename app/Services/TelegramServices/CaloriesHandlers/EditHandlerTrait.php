<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait EditHandlerTrait
{
    protected $messageText;

    protected $replyMarkup;

    protected function saveEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId, $botUser, $callbackQueryId = false)
    {
        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        if ($callbackQueryId) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => __('calories365-bot.changes_saved'),
                'show_alert' => false,
            ]);
        }

        $this->deleteEditingMessage($telegram, $chatId, $messageId);
        $this->clearEditingState($userId);
    }

    protected function exitEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId, $callbackQueryId = false)
    {
        $editingState = Cache::get("user_editing_{$userId}");
        if (isset($editingState['original_product'])) {
            $userProducts[$productId] = $editingState['original_product'];
            Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));
        }

        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        if ($callbackQueryId) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => __('calories365-bot.changes_canceled'),
                'show_alert' => false,
            ]);
        }

        $this->deleteEditingMessage($telegram, $chatId, $messageId);
        $this->clearEditingState($userId);
    }

    protected function clearEditingState($userId)
    {
        Cache::forget("user_editing_{$userId}");
        Cache::forget("command_block{$userId}");
    }

    protected function updateProductMessage($telegram, $chatId, $productData)
    {
        Log::info(print_r($productData, true));
        $messageId = $productData['message_id'];

        $productTranslation = $productData['product_translation'];
        $product = $productData['product'];
        $productId = $productTranslation['id'];

        $this->generateTableBody($product, $productTranslation, $productId);

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $this->messageText,
                'parse_mode' => 'Markdown',
                'reply_markup' => $this->replyMarkup,
            ]);
        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            $errorData = $e->getResponseData();
            if (isset($errorData['description']) && strpos($errorData['description'], 'message is not modified') !== false) {
                Log::info(__('calories365-bot.message_not_modified'));
            } else {
                Log::error('Error updating product message: '.$e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error('Error updating product message: '.$e->getMessage());
        }
    }

    protected function deleteEditingMessage($telegram, $chatId, $messageId)
    {
        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting editing message: '.$e->getMessage());
        }
    }

    protected function editEditingMessage($telegram, $chatId, $messageId, $newText)
    {
        $replyMarkup = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => __('calories365-bot.save'), 'callback_data' => 'editing_save'],
                    ['text' => __('calories365-bot.skip_step'), 'callback_data' => 'editing_skip'],
                    ['text' => __('calories365-bot.cancel'), 'callback_data' => 'editing_cancel'],
                ],
            ],
        ]);

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $newText,
                'reply_markup' => $replyMarkup,
            ]);
        } catch (\Exception $e) {
            Log::error('Error editing message during editing: '.$e->getMessage());
        }
    }

    protected function generateTableBody($product, $productTranslation, $productId)
    {
        $productArray = [
            [__('calories365-bot.calories'),      $product['calories'],      round($product['calories'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.proteins'),      $product['proteins'],      round($product['proteins'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.fats'),          $product['fats'],          round($product['fats'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.carbohydrates'), $product['carbohydrates'], round($product['carbohydrates'] / 100 * $product['quantity_grams'], 1)],
        ];

        $this->messageText = Utilities::generateTable(
            $productTranslation['name'],
            $product['quantity_grams'],
            $productArray,
            $productTranslation['said_name']
        );


        Log::info('messageText');
        Log::info(print_r($this->messageText, true));

        $userId = auth()->user()->id ?? request()->userId ?? null;
        $clickCount = Cache::get("product_click_count_{$userId}_{$productId}", 0);

        // Define the button text based on the scenario
        $saidName = $productTranslation['said_name'] ?? '';
        $originalName = $productTranslation['original_name'] ?? '';

        // / Check if the product was already generated using the OpenAI API
        $wasGeneratedByOpenAI = (isset($product['edited']) && $product['edited'] == 1 &&
                isset($product['verified']) && $product['verified'] == 1) ||
            (isset($product['ai_generated']) && $product['ai_generated'] === true);

        // If the product was already generated via OpenAI or the click count > 0,
        // show "Generate with AI"
        // If this is the first click and the name differs from the original,
        // show "Search"
        $useSearchButton = ! $wasGeneratedByOpenAI && $clickCount === 0 && $saidName !== $originalName && ! empty($originalName);
        $searchButtonText = $useSearchButton
            ? __('calories365-bot.search')
            : __('calories365-bot.generate_with_ai');

        $inlineKeyboard = [
            [
                [
                    'text' => $searchButtonText,
                    'callback_data' => 'search_'.$productId,
                ],
            ],
            [
                [
                    'text' => __('calories365-bot.edit'),
                    'callback_data' => 'edit_'.$productId,
                ],
                [
                    'text' => __('calories365-bot.delete'),
                    'callback_data' => 'destroy_'.$productId,
                ],
            ],
        ];

        $this->replyMarkup = json_encode([
            'inline_keyboard' => $inlineKeyboard,
        ]);

        return true;
    }
}
