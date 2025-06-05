<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery;

use Illuminate\Support\Facades\Cache;

class EditingSkipCallbackQueryHandler extends EditingBaseCallbackQueryHandler
{
    protected function process($bot, $telegram, $callbackQuery, $botUser)
    {
        $this->processSkip(
            $telegram,
            $this->chatId,
            $this->userId,
            $this->editingState,
            $this->userProducts,
            $this->productId,
            $this->messageId,
            $botUser
        );

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => __('calories365-bot.step_skipped'),
            'show_alert' => false,
        ]);
    }

    protected function processSkip($telegram, $chatId, $userId, &$editingState, &$userProducts, $productId, $messageId, $botUser)
    {
        switch ($editingState['step']) {
            case 'awaiting_name':
                $editingState['step'] = 'awaiting_quantity';
                $nextPrompt = __('calories365-bot.please_enter_new_quantity_of_grams');
                break;
            case 'awaiting_quantity':
                $editingState['step'] = 'awaiting_calories';
                $nextPrompt = __('calories365-bot.please_enter_new_calories');
                break;
            case 'awaiting_calories':
                $editingState['step'] = 'awaiting_proteins';
                $nextPrompt = __('calories365-bot.please_enter_new_proteins');
                break;
            case 'awaiting_proteins':
                $editingState['step'] = 'awaiting_fats';
                $nextPrompt = __('calories365-bot.please_enter_new_fats');
                break;
            case 'awaiting_fats':
                $editingState['step'] = 'awaiting_carbohydrates';
                $nextPrompt = __('calories365-bot.please_enter_new_carbohydrates');
                break;
            case 'awaiting_carbohydrates':
                $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId, $botUser);

                return;
            default:
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.error_editing_product'),
                ]);

                return;
        }

        Cache::put("user_editing_{$userId}", $editingState, now()->addMinutes(30));

        $this->editEditingMessage($telegram, $chatId, $messageId, $nextPrompt);
    }
}
