<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\EditingProcessCallbackQuery;

use Illuminate\Support\Facades\Cache;

class EditingSkipCallbackQueryHandler extends EditingBaseCallbackQueryHandler
{
    protected function process($bot, $telegram, $callbackQuery, $botUser)
    {

        $this->processSkip($telegram, $this->chatId, $this->userId, $this->editingState, $this->userProducts, $this->productId, $this->messageId, $botUser);

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => 'Шаг пропущен.',
            'show_alert' => false,
        ]);
    }

    protected function processSkip($telegram, $chatId, $userId, &$editingState, &$userProducts, $productId, $messageId, $botUser)
    {
        switch ($editingState['step']) {
            case 'awaiting_name':
                $editingState['step'] = 'awaiting_quantity';
                $nextPrompt = 'Пожалуйста, введите новое количество грамм.';
                break;
            case 'awaiting_quantity':
                $editingState['step'] = 'awaiting_calories';
                $nextPrompt = 'Пожалуйста, введите новое количество калорий.';
                break;
            case 'awaiting_calories':
                $editingState['step'] = 'awaiting_proteins';
                $nextPrompt = 'Пожалуйста, введите новое количество белков.';
                break;
            case 'awaiting_proteins':
                $editingState['step'] = 'awaiting_fats';
                $nextPrompt = 'Пожалуйста, введите новое количество жиров.';
                break;
            case 'awaiting_fats':
                $editingState['step'] = 'awaiting_carbohydrates';
                $nextPrompt = 'Пожалуйста, введите новое количество углеводов.';
                break;
            case 'awaiting_carbohydrates':
                $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId, $botUser);
                return;
            default:
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при редактировании продукта.',
                ]);
                return;
        }

        Cache::put("user_editing_{$userId}", $editingState, now()->addMinutes(30));

        $this->editEditingMessage($telegram, $chatId, $messageId, $nextPrompt);
    }
}
