<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use App\Services\TelegramServices\MessageHandlers\MessageHandlerInterface;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use function App\Services\TelegramServices\CaloriesHandlers\generateFormattedText;

class EditMessageHandler implements MessageHandlerInterface
{
    use EditHandlerTrait;

    public function handle($bot, $telegram, $message)
    {
        $userId = $message->getFrom()->getId();
        $chatId = $message->getChat()->getId();
        $text = $message->getText();

        // Проверяем, находится ли пользователь в процессе редактирования
        $editingState = Cache::get("user_editing_{$userId}");

        if ($editingState) {
            $productId = $editingState['product_id'];
            $step = $editingState['step'];
            $messageId = $editingState['message_id'];

            // Получаем список продуктов из кеша
            $userProducts = Cache::get("user_products_{$userId}");

            if (!$userProducts || !isset($userProducts[$productId])) {
                // Продукт не найден, очищаем состояние редактирования
                $this->clearEditingState($userId);

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Продукт не найден или истекло время сессии.',
                ]);

                return;
            }

            $productData = $userProducts[$productId];

            $this->processInput($telegram, $chatId, $userId, $text, $editingState, $userProducts, $productId, $messageId);


            // Пытаемся удалить сообщение пользователя (невозможно в личных чатах)
            $this->deleteUserMessage($telegram, $chatId, $message->getMessageId());

            return; // Останавливаем дальнейшую обработку
        } else {
            // Пользователь не в процессе редактирования
            // Можно обработать другие текстовые сообщения или игнорировать
            return;
        }
    }


    protected function processInput($telegram, $chatId, $userId, $text, &$editingState, &$userProducts, $productId, $messageId)
    {
        $currentStep = $editingState['step'];
        $validInput = true;

        switch ($currentStep) {
            case 'awaiting_name':
                // Обновляем название продукта
                $userProducts[$productId]['product_translation']['name'] = $text;
                $userProducts[$productId]['product']['edited'] = 1;
                $nextStep = 'awaiting_quantity';
                $nextPrompt = 'Пожалуйста, введите новое количество грамм.';
                break;
            case 'awaiting_quantity':
                // Обновляем название продукта
                $userProducts[$productId]['product']['quantity_grams'] = $text;
                $userProducts[$productId]['product']['edited'] = 1;
                $nextStep = 'awaiting_calories';
                $nextPrompt = 'Пожалуйста, введите новое количество калорий.';
                break;
            case 'awaiting_calories':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['calories'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_proteins';
                    $nextPrompt = 'Пожалуйста, введите новое количество белков.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для калорий.';
                }
                break;
            case 'awaiting_proteins':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['proteins'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_fats';
                    $nextPrompt = 'Пожалуйста, введите новое количество жиров.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для белков.';
                }
                break;
            case 'awaiting_fats':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['fats'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;
                    $nextStep = 'awaiting_carbohydrates';
                    $nextPrompt = 'Пожалуйста, введите новое количество углеводов.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для жиров.';
                }
                break;
            case 'awaiting_carbohydrates':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['carbohydrates'] = $text;
                    $userProducts[$productId]['product']['edited'] = 1;

                    // Редактирование завершено
                    $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));
                    return;
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для углеводов.';
                }
                break;
            default:
                // Неизвестный шаг, очищаем состояние редактирования
                $this->clearEditingState($userId);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Произошла ошибка при редактировании продукта.',
                ]);
                return;
        }

        if ($validInput) {
            // Сохраняем обновленные данные продукта и состояние редактирования
            Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));

            $editingState['step'] = $nextStep;
            Cache::put("user_editing_{$userId}", $editingState, now()->addMinutes(30));

            // Обновляем сообщение с продуктом
            $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

            // Обновляем сообщение бота с новым запросом
            $this->editEditingMessage($telegram, $chatId, $messageId, $nextPrompt);
        } else {
            // Обновляем сообщение бота с сообщением об ошибке
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
            // Игнорируем ошибку, так как это ожидаемое поведение в личных чатах
        }
    }

}
