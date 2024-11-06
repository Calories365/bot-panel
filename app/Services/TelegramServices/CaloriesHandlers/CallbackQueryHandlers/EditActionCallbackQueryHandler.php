<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EditActionCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    use EditHandlerTrait;

    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();

        // Получаем состояние редактирования
        $editingState = Cache::get("user_editing_{$userId}");

        if (!$editingState) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Сессия редактирования истекла или отсутствует.',
                'show_alert' => true,
            ]);
            return;
        }

        $productId = $editingState['product_id'];

        // Получаем список продуктов
        $userProducts = Cache::get("user_products_{$userId}");

        if (!$userProducts || !isset($userProducts[$productId])) {
            $this->clearEditingState($userId);
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Продукт не найден или истекло время сессии.',
                'show_alert' => true,
            ]);
            return;
        }

        if ($callbackData === 'editing_save') {
            // Сохранить изменения

            $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId,  $callbackQuery->getId());


        } elseif ($callbackData === 'editing_cancel') {
            // Отменить изменения
            $this->exitEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId,  $callbackQuery->getId());

            // Отправляем всплывающее уведомление
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Редактирование отменено.',
                'show_alert' => false,
            ]);
        } elseif ($callbackData === 'editing_skip') {
            // Пропустить текущий шаг
            $this->processSkip($telegram, $chatId, $userId, $editingState, $userProducts, $productId, $messageId);

            // Отправляем уведомление
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Шаг пропущен.',
                'show_alert' => false,
            ]);

        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Неизвестная команда.',
                'show_alert' => true,
            ]);
        }
    }

    // Метод processSkip можно также вынести в трейт, если он используется в обоих классах
    protected function processSkip($telegram, $chatId, $userId, &$editingState, &$userProducts, $productId, $messageId, )
    {
        // Переходим к следующему шагу
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
                // Редактирование завершено
                $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
                return;
            default:
                // Неизвестный шаг, очищаем состояние редактирования
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при редактировании продукта.',
                ]);
                return;
        }

        // Сохраняем обновленное состояние редактирования
        Cache::put("user_editing_{$userId}", $editingState, now()->addMinutes(30));

        // Обновляем сообщение бота с новым запросом
        $this->editEditingMessage($telegram, $chatId, $messageId, $nextPrompt);
    }

    // Метод editEditingMessage также можно вынести в трейт
}
