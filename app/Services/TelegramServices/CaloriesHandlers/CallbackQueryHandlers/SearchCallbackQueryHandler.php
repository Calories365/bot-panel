<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\ChatGPTServices\SpeechToTextService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    use EditHandlerTrait;

    protected DiaryApiService $diaryApiService;
    protected SpeechToTextService $speechToTextService;

    public function __construct(DiaryApiService $diaryApiService, SpeechToTextService $speechToTextService)
    {
        $this->diaryApiService     = $diaryApiService;
        $this->speechToTextService = $speechToTextService;
    }

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $callbackData = $callbackQuery->getData();
        $parts        = explode('_', $callbackData);
        $messageId    = $callbackQuery->getMessage()->getMessageId();
        $locale       = $botUser->locale;
        $calories_id  = $botUser->calories_id;

        if (isset($parts[1])) {
            $productId = $parts[1];
            $chatId    = $callbackQuery->getMessage()->getChat()->getId();
            $userId    = $callbackQuery->getFrom()->getId();

            $products  = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {
                $clickCount = Cache::increment("product_click_count_{$userId}_{$productId}");
                Cache::put("product_click_count_{$userId}_{$productId}", $clickCount, now()->addMinutes(30));

                $saidName      = $products[$productId]['product_translation']['said_name'];
                $quantityGrams = $products[$productId]['product']['quantity_grams'] ?? '';
                $originalName  = $products[$productId]['product_translation']['original_name'] ?? '';

                $formattedText = $saidName . " - " . $quantityGrams . " " . __('calories365-bot.grams');

                try {
                    if ($clickCount > 1) {
                        $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                    } else {
                        if ($saidName !== $originalName) {
                            $response = $this->diaryApiService->getTheMostRelevantProduct($formattedText, $calories_id, $locale);
                            if ($response && isset($response['product'])) {
                                $product = $response['product'];
                            } else {
                                $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                                return;
                            }
                        } else {
                            $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                            return;
                        }
                    }
                } catch (GuzzleException $e) {
                    Log::error("Error generating product data: " . $e->getMessage());
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text'             => __('calories365-bot.error_processing_data'),
                        'show_alert'       => false,
                    ]);
                    return;
                }

                if (isset($product)) {
                    unset($products[$productId]);

                    $newProductId = $product['product_translation']['id'] ?? $productId;

                    $products[$newProductId] = $product;
                    $products[$newProductId]['message_id'] = $messageId;

                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

                    $this->updateProductMessage($telegram, $chatId, $products[$newProductId]);

                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text'             => __('calories365-bot.product_data_updated'),
                        'show_alert'       => false,
                    ]);
                } else {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text'             => __('calories365-bot.product_not_found'),
                        'show_alert'       => false,
                    ]);
                }
            }
        }
    }

    private function generateProductData(&$products, $productId, $userId, $telegram, $chatId, $callbackQuery)
    {
        $saidName = $products[$productId]['product_translation']['said_name'];

        try {
            Log::info('saidName: ' . $saidName);
            $productData = $this->speechToTextService->generateNewProductData($saidName);
            Log::info('generated: ');
            Log::info(print_r($productData, true));
        } catch (GuzzleException $e) {
            Log::error("Error generating product data: " . $e->getMessage());
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'             => __('calories365-bot.error_generating_data'),
                'show_alert'       => false,
            ]);
            return;
        }

        if ($productData && (
            stripos($productData, 'извините') !== false ||
            stripos($productData, 'sorry') !== false ||
            stripos($productData, 'вибачте') !== false ||
            stripos($productData, 'не могу') !== false ||
            stripos($productData, 'cannot') !== false ||
            stripos($productData, 'не можу') !== false ||
            stripos($productData, 'error') !== false ||
            stripos($productData, 'ошибка') !== false ||
            stripos($productData, 'помилка') !== false
        )) {
            Log::error("OpenAI couldn't generate data for product: " . $saidName);
            Log::error("Response: " . $productData);
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'             => __('calories365-bot.cannot_generate_product_data'),
                'show_alert'       => true,
            ]);
            return;
        }

        if ($productData) {
            $newNutritionalData = $this->parseNutritionalData($productData);

            if (empty($newNutritionalData)) {
                Log::error("Failed to parse nutritional data from OpenAI response for product: " . $saidName);
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text'             => __('calories365-bot.cannot_generate_product_data'),
                    'show_alert'       => true,
                ]);
                return;
            }

            if (isset($products[$productId]['product'])) {
                foreach ($newNutritionalData as $key => $value) {
                    $products[$productId]['product'][$key] = $value;
                }
            }

            $products[$productId]['product']['edited'] = 1;
            $products[$productId]['product']['verified'] = 1;
            $products[$productId]['product']['ai_generated'] = true;
            $products[$productId]['product_translation']['name'] = $saidName;

            Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

            $this->updateProductMessage($telegram, $chatId, $products[$productId]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'             => __('calories365-bot.product_data_updated'),
                'show_alert'       => false,
            ]);
        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'             => __('calories365-bot.failed_to_get_product_data'),
                'show_alert'       => true,
            ]);
        }
    }

    private function parseNutritionalData($dataString): array
    {
        $nutritionalData = [];
        $parts = explode(';', $dataString);

        foreach ($parts as $part) {
            $part = trim($part, " ;");
            if (empty($part)) {
                continue;
            }

            $keyValue = explode('-', $part);
            if (count($keyValue) === 2) {
                $key   = trim($keyValue[0]);
                $value = trim($keyValue[1]);

                switch (mb_strtolower($key)) {
                    case mb_strtolower(__('calories365-bot.calories')):
                        $nutritionalData['calories'] = (float) $value;
                        break;
                    case mb_strtolower(__('calories365-bot.proteins')):
                        $nutritionalData['proteins'] = (float) $value;
                        break;
                    case mb_strtolower(__('calories365-bot.fats')):
                    $nutritionalData['fats'] = (float) $value;
                        break;
                    case mb_strtolower(__('calories365-bot.carbohydrates')):
                    $nutritionalData['carbohydrates'] = (float) $value;
                        break;
                }
            }
        }
        return $nutritionalData;
    }
}
