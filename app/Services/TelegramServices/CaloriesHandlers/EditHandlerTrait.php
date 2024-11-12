<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait EditHandlerTrait
{
    protected $messageText;
    protected $replyMarkup;
    protected function saveEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId, $callbackQueryId = false)
    {
        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        if($callbackQueryId){
            $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => 'Изменения сохранены.',
            'show_alert' => false,
        ]);}

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

        if($callbackQueryId){
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => 'Изменения отменены',
                'show_alert' => false,
            ]);}
        $this->deleteEditingMessage($telegram, $chatId, $messageId);

        $this->clearEditingState($userId);
    }

    protected function clearEditingState($userId)
    {
        Cache::forget("user_editing_{$userId}");
        Cache::forget("command_block{$userId}", 0);
    }

    protected function updateProductMessage($telegram, $chatId, $productData)
    {
        Log::info(print_r($productData, true));
        $messageId = $productData['message_id'];


        $productTranslation = $productData['product_translation'];
        $product = $productData['product'];
        $productId = $productTranslation['id'];

        $this->generateTableBody($product, $productTranslation,$productId);

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
                Log::info('Сообщение не изменилось, обновление не требуется.');
            } else {
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


        $this->messageText = Utilities::generateTable($productTranslation['name'] ,$product['quantity_grams'], $productArray , $productTranslation['said_name']);

        $inlineKeyboard = [
            [
                [
                'text' => 'Искать',
                'callback_data' => 'search_' . $productId
            ],

                [
                    'text' => 'Обновить',
                    'callback_data' => 'generate_' . $productId
                ]
            ],
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
        $this->replyMarkup = json_encode([
            'inline_keyboard' => $inlineKeyboard
        ]);
        return true;
    }

//    protected function sentProductsToUser($responseArray, $telegram, $chatId, $userId){
//
//        if (isset($responseArray['message']) && $responseArray['message'] === 'Products found' && !empty($responseArray['products'])) {
//            $products = $responseArray['products'];
//
//            // Инициализируем массив для хранения продуктов с дополнительной информацией
//            $userProducts = [];
//
//            foreach ($products as $index => $productInfo) {
//
//                if (isset($productInfo['product_translation']) && isset($productInfo['product'])) {
//
//                    $productTranslation = $productInfo['product_translation'];
//                    $product = $productInfo['product'];
//                    $productId = $productTranslation['id'];
//                    $this->generateTableBody($product, $productTranslation, $productId);
//
//                    $sentMessage = $telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => $this->messageText,
//                        'parse_mode' => 'Markdown',
//                        'reply_markup' => $this->replyMarkup
//                    ]);
//
//                    $userProducts[$productId] = [
//                        'product_translation' => $productTranslation,
//                        'product' => $product,
//                        'message_id' => $sentMessage->getMessageId()
//                    ];
//
//                } else {
//                    $messageText = ($index + 1) . ". Информация о продукте неполная.\n";
//
//                    $telegram->sendMessage([
//                        'chat_id' => $chatId,
//                        'text' => $messageText,
//                        'parse_mode' => 'Markdown'
//                    ]);
//                }
//            }
//            // Сохраняем список продуктов в кеше с привязкой к userId
//            Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30)); // Время хранения - 30 минут
//
//            // Отправляем сообщение с общими действиями
//            $finalMessageText = "Действия с продуктами:\n";
//
//            $finalInlineKeyboard = [
//                [
//                    [
//                        'text' => 'Сохранить',
//                        'callback_data' => 'save'
//                    ],
//                    [
//                        'text' => 'Отменить',
//                        'callback_data' => 'cancel'
//                    ]
//                ]
//            ];
//
//            $finalReplyMarkup = json_encode([
//                'inline_keyboard' => $finalInlineKeyboard
//            ]);
//
//            $finalMessage = $telegram->sendMessage([
//                'chat_id' => $chatId,
//                'text' => $finalMessageText,
//                'parse_mode' => 'Markdown',
//                'reply_markup' => $finalReplyMarkup
//            ]);
//            $finalMessageId = $finalMessage->getMessageId();
//
//            Cache::put("user_final_message_id_{$userId}", $finalMessageId, now()->addMinutes(30));
//        } else {
//            $telegram->sendMessage([
//                'chat_id' => $chatId,
//                'text' => $responseArray['message'] ?? 'Продукты не найдены.'
//            ]);
//        }
//    }

    }
