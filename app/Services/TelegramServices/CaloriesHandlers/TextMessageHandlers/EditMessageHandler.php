<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EditMessageHandler implements MessageHandlerInterface
{
    use EditHandlerTrait;

    public function handle($bot, $telegram, $message, $botUser)
    {
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();
        $text   = $message->getText();

        $editingState = Cache::get("user_editing_{$userId}");
        if ($editingState) {
            $productId   = $editingState['product_id'];
            $step        = $editingState['step'];
            $messageId   = $editingState['message_id'];
            $userProducts = Cache::get("user_products_{$userId}");

            if (!$userProducts || !isset($userProducts[$productId])) {
                $this->clearEditingState($userId);

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => __('calories365-bot.product_not_found'),
                ]);

                return;
            }

            $this->processInput(
                $telegram,
                $chatId,
                $userId,
                $text,
                $editingState,
                $userProducts,
                $productId,
                $messageId,
                $botUser
            );

            $this->deleteUserMessage($telegram, $chatId, $message->getMessageId());

            return;
        } else {
            return;
        }
    }

    protected function processInput($telegram, $chatId, $userId, $text, &$editingState, &$userProducts, $productId, $messageId, $botUser)
    {
        $currentStep = $editingState['step'];
        $validInput  = true;

        switch ($currentStep) {
            case 'awaiting_name':
                if (strlen($text) <= 50) {
                    $userProducts[$productId]['product_translation']['name']      = $text;
                    $userProducts[$productId]['product_translation']['said_name'] = $text;
                    $userProducts[$productId]['product']['edited']               = 1;

                    Cache::forget("product_click_count_{$userId}_{$productId}");

                    $nextStep   = 'awaiting_quantity';
                    $nextPrompt = __('calories365-bot.please_enter_new_quantity_of_grams');
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.value_too_long');
                }
                break;

            case 'awaiting_quantity':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['quantity_grams'] = $text;
                    $nextStep   = 'awaiting_calories';
                    $nextPrompt = __('calories365-bot.please_enter_new_calories');
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.enter_valid_numeric_value_for_grams');
                }
                break;

            case 'awaiting_calories':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['calories'] = $text;
                    $userProducts[$productId]['product']['edited']   = 1;

                    $nextStep   = 'awaiting_proteins';
                    $nextPrompt = __('calories365-bot.please_enter_new_proteins');
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.enter_valid_numeric_value_for_calories');
                }
                break;

            case 'awaiting_proteins':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['proteins'] = $text;
                    $userProducts[$productId]['product']['edited']   = 1;

                    $nextStep   = 'awaiting_fats';
                    $nextPrompt = __('calories365-bot.please_enter_new_fats');
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.enter_valid_numeric_value_for_proteins');
                }
                break;

            case 'awaiting_fats':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['fats']   = $text;
                    $userProducts[$productId]['product']['edited'] = 1;

                    $nextStep   = 'awaiting_carbohydrates';
                    $nextPrompt = __('calories365-bot.please_enter_new_carbohydrates');
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.enter_valid_numeric_value_for_fats');
                }
                break;

            case 'awaiting_carbohydrates':
                if (is_numeric($text) && $text > -1 && $text <= 1250) {
                    $userProducts[$productId]['product']['carbohydrates'] = $text;
                    $userProducts[$productId]['product']['edited']        = 1;

                    $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId, $botUser);
                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));
                    return;
                } else {
                    $validInput   = false;
                    $errorMessage = __('calories365-bot.enter_valid_numeric_value_for_carbohydrates');
                }
                break;

            default:
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => __('calories365-bot.error_editing_product'),
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
        try {
            $telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
        }
    }
}
