<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use Illuminate\Support\Facades\Cache;

class EditMessageHandler implements MessageHandlerInterface
{
    use EditHandlerTrait;

    public function handle($bot, $telegram, $message)
    {
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        $editingState = Cache::get("user_editing_{$userId}");

        if ($editingState) {
            $productId = $editingState['product_id'];
            $step = $editingState['step'];
            $messageId = $editingState['message_id'];

            $userProducts = Cache::get("user_products_{$userId}");

            if (!$userProducts || !isset($userProducts[$productId])) {
                $this->clearEditingState($userId);

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Продукт не найден или истекло время сессии.',
                ]);

                return;
            }

            $productData = $userProducts[$productId];

            $this->processInput($telegram, $chatId, $userId, $text, $editingState, $userProducts, $productId, $messageId);


            $this->deleteUserMessage($telegram, $chatId, $message->getMessageId());

            return;
        } else {
            return;
        }
    }

    protected function processInput($telegram, $chatId, $userId, $text, &$editingState, &$userProducts, $productId, $messageId)
    {
        $currentStep = $editingState['step'];
        $validInput = true;

        switch ($currentStep) {
            case 'awaiting_name':
                if (strlen($text) <= 50){
                $userProducts[$productId]['product_translation']['name'] = $text;
                $userProducts[$productId]['product_translation']['said_name'] = $text;
                $userProducts[$productId]['product']['edited'] = 1;
                Cache::forget("product_click_count_{$userId}_{$productId}");
                $nextStep = 'awaiting_quantity';
                $nextPrompt = 'Пожалуйста, введите новое количество грамм.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Значение слишком длинное';
                }
                break;
            case 'awaiting_quantity':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['quantity_grams'] = $text;
                    $nextStep = 'awaiting_calories';
                    $nextPrompt = 'Пожалуйста, введите новое количество калорий.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите корректное числовое значение для грамм.';
                    }
                break;
            case 'awaiting_calories':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['calories'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_proteins';
                    $nextPrompt = 'Пожалуйста, введите новое количество белков.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите корректное числовое значение для калорий.';
                }
                break;
            case 'awaiting_proteins':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['proteins'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_fats';
                    $nextPrompt = 'Пожалуйста, введите новое количество жиров.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите корректное числовое значение для белков.';
                }
                break;
            case 'awaiting_fats':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['fats'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_carbohydrates';
                    $nextPrompt = 'Пожалуйста, введите новое количество углеводов.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите корректное числовое значение для жиров.';
                }
                break;
            case 'awaiting_carbohydrates':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['carbohydrates'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;

                    $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));
                    return;
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите корректное числовое значение для углеводов.';
                }
                break;
            default:
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при редактировании продукта.',
                ]);
                return;
        }

        if ($validInput) {
            Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));

            $editingState['step'] = $nextStep;
            Cache::put("user_editing_{$userId}", $editingState, now()->addMinutes(30));

            $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

            $this->editEditingMessage($telegram, $chatId, $messageId, $nextPrompt);
        } else {
            $this->editEditingMessage($telegram, $chatId, $messageId, $errorMessage);
        }
    }

    protected function deleteUserMessage($telegram, $chatId, $messageId)
    {
        // В личных чатах бот не может удалять сообщения пользователя
        // Если бот в группе и является администратором с правами удаления сообщений, то можно попытаться удалить
        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
        }
    }

}
