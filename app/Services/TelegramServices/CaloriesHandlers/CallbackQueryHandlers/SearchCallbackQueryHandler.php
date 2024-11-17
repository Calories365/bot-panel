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
        $this->diaryApiService = $diaryApiService;
        $this->speechToTextService = $speechToTextService;
    }

    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);
        $messageId = $callbackQuery->getMessage()->getMessageId();

        if (isset($parts[1])) {
            $productId = $parts[1];
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $userId = $callbackQuery->getFrom()->getId();

            $products = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {
//                Log::info('before searching');
//                Log::info("product_click_count_{$userId}_{$productId}");
                $clickCount = Cache::increment("product_click_count_{$userId}_{$productId}");
                Cache::put("product_click_count_{$userId}_{$productId}", $clickCount, now()->addMinutes(30));

                $saidName = $products[$productId]['product_translation']['said_name'];
                $quantityGrams = $products[$productId]['product']['quantity_grams'] ?? '';
                $originalName = $products[$productId]['product_translation']['original_name'] ?? '';
                $formattedText = $saidName . " - " . $quantityGrams . " грамм";

                try {
//                    Log::info('$clickCount');
//                    Log::info($clickCount);
                    if ($clickCount > 1) {
//                        Log::info('generating, $clickCount > 1');
                        $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                    } else {
//                        Log::info('$saidName != $originalName');
//                        Log::info($saidName. ', ' . $originalName);
                        if ($saidName != $originalName) {
                            $response = $this->diaryApiService->getTheMostRelevantProduct($formattedText);
//                            Log::info('$response');
//                            Log::info(print_r($response, true));
                            if (isset($response['product'])) {
                                $product = $response['product'];
                            } else {
//                                Log::info('generating, product not found');
                                $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                                return;
                            }
                        } else {
//                            Log::info('generating, $saidName !== $originalName');
                            $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                            return;
                        }
                    }
                } catch (GuzzleException $e) {
                    Log::error("Error generating product data: " . $e->getMessage());
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text' => 'Произошла ошибка при обработке данных.',
                        'show_alert' => false,
                    ]);
                    return;
                }

                if (isset($product)) {
//                    Log::info('Product after search:', $product);

                    unset($products[$productId]);
                    $newProductId = $product['product_translation']['id'] ?? $productId;

                    $products[$newProductId] = $product;
                    $products[$newProductId]['message_id'] = $messageId;

                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

                    $this->updateProductMessage($telegram, $chatId, $products[$newProductId]);

                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text' => 'Данные продукта обновлены.',
                        'show_alert' => false,
                    ]);
                } else {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text' => 'Продукт не найден.',
                        'show_alert' => false,
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
                'text' => 'Произошла ошибка генерации данных.',
                'show_alert' => false,
            ]);
            return;
        }

        if ($productData) {
            $newNutritionalData = $this->parseNutritionalData($productData);

            if (isset($products[$productId]['product'])) {
                foreach ($newNutritionalData as $key => $value) {
                    $products[$productId]['product'][$key] = $value;
                }
            }

            $products[$productId]['product']['edited'] = 1;
            $products[$productId]['product_translation']['name'] = $saidName;

            Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

            $this->updateProductMessage($telegram, $chatId, $products[$productId]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Данные продукта обновлены.',
                'show_alert' => false,
            ]);
        } else {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'Не удалось получить данные продукта.',
                'show_alert' => false,
            ]);
        }
    }

    private function parseNutritionalData($dataString): array
    {
        $nutritionalData = [];
        $parts = explode(';', $dataString);

        foreach ($parts as $part) {
            $part = trim($part, " ;");
            if (empty($part)) continue;

            $keyValue = explode('-', $part);
            if (count($keyValue) === 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);

                switch (mb_strtolower($key)) {
                    case 'калории':
                        $nutritionalData['calories'] = (float)$value;
                        break;
                    case 'белки':
                        $nutritionalData['proteins'] = (float)$value;
                        break;
                    case 'жиры':
                        $nutritionalData['fats'] = (float)$value;
                        break;
                    case 'углеводы':
                        $nutritionalData['carbohydrates'] = (float)$value;
                        break;
                }
            }
        }

        return $nutritionalData;
    }
}
