<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\MessageHandlers\MessageHandlerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EditMessageHandler implements MessageHandlerInterface
{
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

            Log::info('ПРОДУКТЫ: ');
            Log::info(print_r($userProducts, true));

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

            if ($text === '/skip') {
                // Обрабатываем команду /skip
                $this->processSkip($telegram, $chatId, $userId, $editingState, $userProducts, $productId, $messageId);
            } elseif ($text === '/exit' || $text === '/cancel') {
                // Пользователь хочет выйти из режима редактирования без сохранения
                $this->exitEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
                return;
            } elseif ($text === '/save') {
                // Пользователь хочет сохранить изменения и выйти
                $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
                return;
            } else {
                // Обрабатываем ввод пользователя
                $this->processInput($telegram, $chatId, $userId, $text, $editingState, $userProducts, $productId, $messageId);
            }

            // Пытаемся удалить сообщение пользователя (невозможно в личных чатах)
            $this->deleteUserMessage($telegram, $chatId, $message->getMessageId());

            return; // Останавливаем дальнейшую обработку
        } else {
            // Пользователь не в процессе редактирования
            // Можно обработать другие текстовые сообщения или игнорировать
            return;
        }
    }

    protected function processSkip($telegram, $chatId, $userId, &$editingState, &$userProducts, $productId, $messageId)
    {
        // Переходим к следующему шагу
        switch ($editingState['step']) {
            case 'awaiting_name':
                $editingState['step'] = 'awaiting_calories';
                $nextPrompt = 'Пожалуйста, введите новое количество количество грамм или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_quantity':
                $editingState['step'] = 'awaiting_quantity';
                $nextPrompt = 'Пожалуйста, введите новое количество калорий или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_calories':
                $editingState['step'] = 'awaiting_proteins';
                $nextPrompt = 'Пожалуйста, введите новое количество белков или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_proteins':
                $editingState['step'] = 'awaiting_fats';
                $nextPrompt = 'Пожалуйста, введите новое количество жиров или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_fats':
                $editingState['step'] = 'awaiting_carbohydrates';
                $nextPrompt = 'Пожалуйста, введите новое количество углеводов или отправьте /skip, чтобы оставить без изменений.';
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

    protected function processInput($telegram, $chatId, $userId, $text, &$editingState, &$userProducts, $productId, $messageId)
    {
        $currentStep = $editingState['step'];
        $validInput = true;

        switch ($currentStep) {
            case 'awaiting_name':
                // Обновляем название продукта
                $userProducts[$productId]['product_translation']['name'] = $text;
                $nextStep = 'awaiting_quantity';
                $nextPrompt = 'Пожалуйста, введите новое количество грамм или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_quantity':
                // Обновляем название продукта
                $userProducts[$productId]['product']['quantity_grams'] = $text;
                $nextStep = 'awaiting_calories';
                $nextPrompt = 'Пожалуйста, введите новое количество калорий или отправьте /skip, чтобы оставить без изменений.';
                break;
            case 'awaiting_calories':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['calories'] = $text;
                    $nextStep = 'awaiting_proteins';
                    $nextPrompt = 'Пожалуйста, введите новое количество белков или отправьте /skip, чтобы оставить без изменений.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для калорий.';
                }
                break;
            case 'awaiting_proteins':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['proteins'] = $text;
                    $nextStep = 'awaiting_fats';
                    $nextPrompt = 'Пожалуйста, введите новое количество жиров или отправьте /skip, чтобы оставить без изменений.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для белков.';
                }
                break;
            case 'awaiting_fats':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['fats'] = $text;
                    $nextStep = 'awaiting_carbohydrates';
                    $nextPrompt = 'Пожалуйста, введите новое количество углеводов или отправьте /skip, чтобы оставить без изменений.';
                } else {
                    $validInput = false;
                    $errorMessage = 'Пожалуйста, введите числовое значение для жиров.';
                }
                break;
            case 'awaiting_carbohydrates':
                if (is_numeric($text)) {
                    $userProducts[$productId]['product']['carbohydrates'] = $text;

                    // Редактирование завершено
                    $this->saveEditing($telegram, $chatId, $userId, $userProducts, $productId, $messageId);
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

    protected function saveEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId)
    {
        // Обновляем сообщение с продуктом, чтобы отобразить финальные изменения
        $this->updateProductMessage($telegram, $chatId, $userProducts[$productId]);

        // Удаляем сообщение редактирования
        $this->deleteEditingMessage($telegram, $chatId, $messageId);

        // Очищаем состояние редактирования
        $this->clearEditingState($userId);

        // Уведомляем пользователя о сохранении изменений
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Изменения сохранены.',
        ]);
    }

    protected function exitEditing($telegram, $chatId, $userId, &$userProducts, $productId, $messageId)
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

        // Удаляем сообщение редактирования
        $this->deleteEditingMessage($telegram, $chatId, $messageId);

        // Очищаем состояние редактирования
        $this->clearEditingState($userId);

        // Уведомляем пользователя
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Редактирование продукта отменено. Изменения не сохранены.',
        ]);
    }

    protected function editEditingMessage($telegram, $chatId, $messageId, $newText)
    {
        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $newText,
            ]);
        } catch (\Exception $e) {
            Log::error("Error editing message during editing: " . $e->getMessage());
        }
    }
//    protected function editEditingMessage($telegram, $chatId, $messageId, $newText)
//    {
//        $replyMarkup = json_encode([
//            'inline_keyboard' => [
//                [
//                    ['text' => 'Сохранить', 'callback_data' => 'edit_save'],
//                    ['text' => 'Пропустить шаг', 'callback_data' => 'edit_skip'],
//                    ['text' => 'Отменить', 'callback_data' => 'edit_cancel'],
//                ]
//            ]
//        ]);
//
//        try {
//            $telegram->editMessageText([
//                'chat_id' => $chatId,
//                'message_id' => $messageId,
//                'text' => $newText,
//                'reply_markup' => $replyMarkup,
//            ]);
//        } catch (\Exception $e) {
//            Log::error("Error editing message during editing: " . $e->getMessage());
//        }
//    }

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

    protected function clearEditingState($userId)
    {
        Cache::forget("user_editing_{$userId}");
    }

    protected function updateProductMessage($telegram, $chatId, $productData)
    {
        $messageId = $productData['message_id'];

        $productTranslation = $productData['product_translation'];
        $product = $productData['product'];

        $messageText = "*" . $productTranslation['name'] . "*\n";
        $messageText .= "Количество: " . ($product['quantity_grams'] ?? '—') . " грамм\n";
        $messageText .= "Калории: " . ($product['calories'] ?? '—') . " ккал\n";
        $messageText .= "Белки: " . ($product['proteins'] ?? '—') . " г\n";
        $messageText .= "Жиры: " . ($product['fats'] ?? '—') . " г\n";
        $messageText .= "Углеводы: " . ($product['carbohydrates'] ?? '—') . " г\n";

        $inlineKeyboard = [
            [
                [
                    'text' => 'Изменить',
                    'callback_data' => 'edit_' . $productTranslation['id']
                ],
                [
                    'text' => 'Удалить',
                    'callback_data' => 'delete_' . $productTranslation['id']
                ]
            ]
        ];

        $replyMarkup = json_encode([
            'inline_keyboard' => $inlineKeyboard
        ]);

        try {
            $telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $messageText,
                'parse_mode' => 'Markdown',
                'reply_markup' => $replyMarkup,
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating product message: " . $e->getMessage());
        }
    }
}
