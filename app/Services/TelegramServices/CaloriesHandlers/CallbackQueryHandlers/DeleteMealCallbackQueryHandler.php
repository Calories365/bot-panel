<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\DiaryApiService;
use Illuminate\Support\Facades\Log;

class DeleteMealCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        if (isset($parts[2])) {
            $mealId = $parts[2];

            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();

            // Отправляем запрос на удаление продукта в дневнике
            Log::info('try');
            $response = $this->diaryApiService->deleteMeal($mealId);
            Log::info($response);
            if (isset($response['error'])) {
                Log::error('Error deleting meal: ' . $response['error']);
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Ошибка при удалении продукта.',
                    'show_alert' => true,
                ]);
            } else {
                // Удаляем сообщение с продуктом
                try {
                    $telegram->deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                } catch (\Exception $e) {
                    Log::error("Error deleting meal message: " . $e->getMessage());
                }

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Продукт удалён.',
                    'show_alert' => false,
                ]);
            }
        }
    }
}
