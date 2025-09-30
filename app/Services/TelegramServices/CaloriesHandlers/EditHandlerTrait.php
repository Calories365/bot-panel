<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Models\BotUser;
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

    protected function updateProductMessage($telegram, $chatId, $productData): void
    {
        $messageId = $productData['message_id'];

        $productTranslation = $productData['product_translation'];
        $product = $productData['product'];
        $productId = $productTranslation['id'];

        $useBigFont = false;
        try {
            $botUser = BotUser::where('telegram_id', $chatId)->first();
            $useBigFont = (bool) ($botUser->big_font ?? false);
        } catch (\Throwable $e) {
            Log::error('Failed to load BotUser for big_font: '.$e->getMessage());
        }

        $this->generateTableBody($product, $productTranslation, $productId, $useBigFont);

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

    protected function deleteEditingMessage($telegram, $chatId, $messageId): void
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

    protected function editEditingMessage($telegram, $chatId, $messageId, $newText): void
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

    protected function generateTableBody($product, $productTranslation, $productId, bool $useBigFont): true
    {
        $productArray = [
            [__('calories365-bot.calories'),      $product['calories'],      round($product['calories'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.proteins'),      $product['proteins'],      round($product['proteins'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.fats'),          $product['fats'],          round($product['fats'] / 100 * $product['quantity_grams'], 1)],
            [__('calories365-bot.carbohydrates'), $product['carbohydrates'], round($product['carbohydrates'] / 100 * $product['quantity_grams'], 1)],
        ];

        if ($useBigFont) {
            $this->messageText = Utilities::generateTableForBigFont(
                $productTranslation['name'],
                $product['quantity_grams'],
                $productArray,
                $productTranslation['said_name']
            );
        } else {
            $this->messageText = Utilities::generateTable(
                $productTranslation['name'],
                $product['quantity_grams'],
                $productArray,
                $productTranslation['said_name']
            );
        }

        $userId = auth()->user()->id ?? request()->userId ?? null;
        $clickCount = Cache::get("product_click_count_{$userId}_{$productId}", 0);

        $saidName = $productTranslation['said_name'] ?? '';
        $originalName = $productTranslation['original_name'] ?? '';

        $wasGeneratedByOpenAI = (isset($product['edited']) && $product['edited'] == 1 &&
                isset($product['verified']) && $product['verified'] == 1) ||
            (isset($product['ai_generated']) && $product['ai_generated'] === true);

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
