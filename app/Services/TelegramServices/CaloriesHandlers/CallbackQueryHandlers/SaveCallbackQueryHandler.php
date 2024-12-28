<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\DiaryApiService;

class SaveCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;
    protected DiaryApiService $diaryApiService;

    public function __construct()
    {
        $this->diaryApiService = new DiaryApiService();
    }

    public function handle($bot, $telegram, $callbackQuery, $locale)
    {
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $callbackData = $callbackQuery->getData();

        $parts = explode('_', $callbackData);

        if(count($parts) > 1){
            $partOfTheDay = $parts[1];
        }

        $data = Cache::get("user_products_{$userId}");
        if (!$data){
            return;
        }
        $diaryUserId = 32;
        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbohydrates' => 0,
        ];

        foreach ($data as $productData) {
            $product = $productData['product'];
            $productTranslation = $productData['product_translation'];

            $total['calories'] += round($product['calories'] * $product['quantity_grams'] / 100);
            $total['proteins'] += round($product['proteins'] * $product['quantity_grams'] / 100);
            $total['fats'] += round($product['fats'] * $product['quantity_grams'] / 100);
            $total['carbohydrates'] += round($product['carbohydrates'] * $product['quantity_grams'] / 100);

            if (isset($product['edited']) && $product['edited'] == 1) {
                $this->saveProduct($product, $productTranslation, $diaryUserId, $partOfTheDay, $chatId, $locale);
            } else {
                $this->saveFoodConsumption($product, $diaryUserId, $partOfTheDay, $chatId, $locale);
            }
//            Log::info('before deleting');
//            Log::info("product_click_count_{$userId}_{$productTranslation['id']}");
            Cache::forget("product_click_count_{$userId}_{$productTranslation['id']}");
        }

        $productArray = [
            [ "Калории", $total['calories']],
            [ "Белки",$total['proteins']],
            [ "Жиры", $total['fats']],
            [ "Углеводы", $total['carbohydrates']],
        ];
        $messageText = Utilities::generateTableType2('Данные сохранены, Вы употребили' , $productArray) . "\n\n";

        Cache::forget("user_products_{$userId}");
        Cache::forget("user_final_message_id_{$userId}");

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $messageText,
            'parse_mode' => 'Markdown',

        ]);

        $this->deleteProductMessages($telegram, $chatId, $data, $callbackQuery);

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
    }

    protected function saveProduct($product, $productTranslation, $diaryUserId, $partOfTheDay,$chat_id, $locale)
    {
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
//            'consumed_at' =>  date('Y-m-d', strtotime('-1 day')),
            'part_of_day' => $partOfTheDay,
        ];

        $response = $this->diaryApiService->saveProduct($postData,$chat_id, $locale);

        if (isset($response['error'])) {
            Log::error('Error saving product: ' . $response['error']);
        } else {
            Log::info('Product saved successfully.');
        }
    }

    protected function saveFoodConsumption($product, $diaryUserId, $partOfTheDay, $chat_id, $locale)
    {


        $postData = [
            'user_id'      => $diaryUserId,
            'food_id'      => $product['id'],
            'quantity'     => $product['quantity_grams'],
            'consumed_at'  => date('Y-m-d'),
//            'consumed_at' =>  date('Y-m-d', strtotime('-1 day')),
            'part_of_day'  => $partOfTheDay,
        ];


        $response = $this->diaryApiService->saveFoodConsumption($postData, $chat_id, $locale);

        if (isset($response['error'])) {
            Log::error('Error saving food consumption: ' . $response['error']);
        } else {
            Log::info('Food consumption saved successfully.');
        }
    }

    protected function deleteProductMessages($telegram, $chatId, $data, $callbackQuery)
    {
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
    private function getPartOfTheDay(): string
    {

        $currentHour = (int)date('G');

        if ($currentHour >= 6 && $currentHour < 12) {
            $partOfDay = 'morning';
        } elseif ($currentHour >= 12 && $currentHour < 18) {
            $partOfDay = 'dinner';
        } else {
            $partOfDay = 'supper';
        }
        return $partOfDay;
    }
}
