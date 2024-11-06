<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait EditHandlerTrait
{
    protected function saveEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId, $callbackQueryId = false)
    {
        // Обновляем сообщение с продуктом, чтобы отобразить финальные изменения
        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        if($callbackQueryId){
            $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => 'Изменения сохранены.',
            'show_alert' => false,
        ]);}

        // Удаляем сообщение редактирования
        $this->deleteEditingMessage($telegram, $chatId, $messageId);

        // Очищаем состояние редактирования
        $this->clearEditingState($userId);
    }

    protected function exitEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId, $callbackQueryId = false)
    {
        // Восстанавливаем исходные данные продукта из состояния редактирования
        $editingState = Cache::get("user_editing_{$userId}");
        if (isset($editingState['original_product'])) {
            $userProducts[$productId] = $editingState['original_product'];
            // Сохраняем обратно в кеш
            Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));
        }

        // Обновляем сообщение с продуктом, чтобы отобразить исходные данные
        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        if($callbackQueryId){
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => 'Изменения отменены',
                'show_alert' => false,
            ]);}
        // Удаляем сообщение редактирования
        $this->deleteEditingMessage($telegram, $chatId, $messageId);

        // Очищаем состояние редактирования
        $this->clearEditingState($userId);
    }

    protected function clearEditingState($userId)
    {
        Cache::forget("user_editing_{$userId}");
    }

    protected function updateProductMessage($telegram, $chatId, $productData)
    {
        $messageId = $productData['message_id'];


        $productTranslation = $productData['product_translation'];
        $product = $productData['product'];
        $productId = $productTranslation['id'];

        $data = $this->generateTableBody($product, $productTranslation,$productId);

        $messageText = $data[0];
        $replyMarkup = $data[1];

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
                'reply_markup' => $replyMarkup,
            ]);
        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            $errorData = $e->getResponseData();
            if (isset($errorData['description']) && strpos($errorData['description'], 'message is not modified') !== false) {
                // Сообщение не изменилось, игнорируем ошибку
                Log::info('Сообщение не изменилось, обновление не требуется.');
            } else {
                // Логируем другие ошибки
                Log::error("Error updating product message: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error("Error updating product message: " . $e->getMessage());
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
            Log::error("Error deleting editing message: " . $e->getMessage());
        }
    }

    protected function editEditingMessage($telegram, $chatId, $messageId, $newText)
    {
        $replyMarkup = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'Сохранить', 'callback_data' => 'editing_save'],
                    ['text' => 'Пропустить шаг', 'callback_data' => 'editing_skip'],
                    ['text' => 'Отменить', 'callback_data' => 'editing_cancel'],
                ]
            ]
        ]);

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $newText,
                'reply_markup' => $replyMarkup,
            ]);
        } catch (\Exception $e) {
            Log::error("Error editing message during editing: " . $e->getMessage());
        }
    }

    protected function generateTableBody($product, $productTranslation, $productId){

        $productArray = [
            [ "Калории", $product['calories'],round($product['calories']/100*$product['quantity_grams'] ,1)],
            [ "Белки", $product['proteins'],round($product['proteins']/100*$product['quantity_grams'] ,1)],
            [ "Жиры", $product['fats'],round($product['fats']/100*$product['quantity_grams'] ,1)],
            [ "Углеводы", $product['carbohydrates'],round($product['carbohydrates']/100*$product['quantity_grams'] ,1)],
        ];


        $messageText = Utilities::generateTable($productTranslation['name'] ,$product['quantity_grams'], $productArray);

        $inlineKeyboard = [
            [
                [
                    'text' => 'Изменить',
                    'callback_data' => 'edit_' . $productId
                ],
                [
                    'text' => 'Удалить',
                    'callback_data' => 'delete_' . $productId
                ]
            ]
        ];

        $replyMarkup = json_encode([
            'inline_keyboard' => $inlineKeyboard
        ]);
        return [$messageText, $replyMarkup];
    }

    }
