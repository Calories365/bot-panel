<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\DiaryApiService;

class SaveCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    protected $diaryApiService;

    public function __construct()
    {
        $this->diaryApiService = new DiaryApiService();
    }

    public function handle($bot, $telegram, $callbackQuery)
    {
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        // Получаем данные из кеша
        $data = Cache::get("user_products_{$userId}");
        if (!$data){
            return;
        }
        // Жестко заданный user_id
        $diaryUserId = 32;

        // Логируем данные для отладки
        Log::info('saving: ');
        Log::info(print_r($data, true));

        // Обрабатываем каждый продукт
        foreach ($data as $productData) {
            $product = $productData['product'];
            $productTranslation = $productData['product_translation'];

            if (isset($product['edited']) && $product['edited'] == 1) {
                // Продукт был изменён, создаём новый продукт
                $this->saveProduct($product, $productTranslation, $diaryUserId);
            } else {
                // Продукт не был изменён, создаём запись о потреблении
                $this->saveFoodConsumption($product, $diaryUserId);
            }
        }

        // Очищаем кеш
        Cache::forget("user_products_{$userId}");
        Cache::forget("user_final_message_id_{$userId}");

        // Отправляем сообщение пользователю
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Ваши данные успешно сохранены.',
        ]);

        // Удаляем сообщения с продуктами и кнопками действий
        $this->deleteProductMessages($telegram, $chatId, $data, $callbackQuery);

        // Отвечаем на callback_query, чтобы убрать "часики" у пользователя
        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
    }

    protected function saveProduct($product, $productTranslation, $diaryUserId)
    {
        // Подготавливаем данные
        $postData = [
            'user_id' => $diaryUserId,
            'name' => $productTranslation['name'],
            'calories' => $product['calories_per_100g'] ?? $product['calories'],
            'carbohydrates' => $product['carbohydrates_per_100g'] ?? $product['carbohydrates'],
            'fats' => $product['fats_per_100g'] ?? $product['fats'],
            'fibers' => $product['fibers_per_100g'] ?? $product['fibers'] ?? 0,
            'proteins' => $product['proteins_per_100g'] ?? $product['proteins'],
            'quantity' => $product['quantity_grams'],
            'consumed_at' => date('Y-m-d'),
            'part_of_day' => 'morning', // Или получите от пользователя
        ];

        // Используем DiaryApiService для отправки запроса
        $response = $this->diaryApiService->saveProduct($postData);

        if (isset($response['error'])) {
            // Обрабатываем ошибку
            Log::error('Error saving product: ' . $response['error']);
        } else {
            // Обрабатываем успешный ответ
            Log::info('Product saved successfully.');
        }
    }

    protected function saveFoodConsumption($product, $diaryUserId)
    {
        // Подготавливаем данные
        $postData = [
            'user_id' => $diaryUserId,
            'food_id' => $product['id'],
            'quantity' => $product['quantity_grams'],
            'consumed_at' => date('Y-m-d'),
            'part_of_day' => 'morning', // Или получите от пользователя
        ];

        // Используем DiaryApiService для отправки запроса
        $response = $this->diaryApiService->saveFoodConsumption($postData);

        if (isset($response['error'])) {
            // Обрабатываем ошибку
            Log::error('Error saving food consumption: ' . $response['error']);
        } else {
            // Обрабатываем успешный ответ
            Log::info('Food consumption saved successfully.');
        }
    }

    protected function deleteProductMessages($telegram, $chatId, $data, $callbackQuery)
    {
        // Удаляем сообщения с продуктами
        foreach ($data as $productData) {
            if (isset($productData['message_id'])) {
                try {
                    $telegram->deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $productData['message_id'],
                    ]);
                } catch (\Exception $e) {
                    Log::error("Error deleting product message: " . $e->getMessage());
                }
            }
        }

        // Удаляем сообщение с кнопками действий (Сохранить/Отменить)
        $finalMessageId = $callbackQuery->getMessage()->getMessageId();
        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $finalMessageId,
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting final action message: " . $e->getMessage());
        }
    }
}
