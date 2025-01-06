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
                // Увеличиваем счётчик кликов для данного продукта
                $clickCount = Cache::increment("product_click_count_{$userId}_{$productId}");
                Cache::put("product_click_count_{$userId}_{$productId}", $clickCount, now()->addMinutes(30));

                $saidName      = $products[$productId]['product_translation']['said_name'];
                $quantityGrams = $products[$productId]['product']['quantity_grams'] ?? '';
                $originalName  = $products[$productId]['product_translation']['original_name'] ?? '';

                // Формируем текст для поиска, учитывая количество граммов (грамм можно также локализовать при необходимости)
                $formattedText = $saidName . " - " . $quantityGrams . " " . __('calories365-bot.grams');

                try {
                    // Если пользователь кликает по продукту второй раз и более — сразу генерируем данные
                    if ($clickCount > 1) {
                        $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                    } else {
                        // При первом нажатии проверяем, есть ли смысл уточнять продукт по API
                        if ($saidName !== $originalName) {
                            $response = $this->diaryApiService->getTheMostRelevantProduct($formattedText, $calories_id, $locale);

                            if (isset($response['product'])) {
                                $product = $response['product'];
                            } else {
                                // Если из API ничего не вернулось, генерируем данные локально
                                $this->generateProductData($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                                return;
                            }
                        } else {
                            // Если сказанное имя совпадает с оригинальным, тоже генерируем данные локально
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

                // Если API вернул $product
                if (isset($product)) {
                    // Удаляем старый продукт из массива
                    unset($products[$productId]);

                    // В качестве нового ключа подставляем ID, который вернул API
                    $newProductId = $product['product_translation']['id'] ?? $productId;

                    $products[$newProductId] = $product;
                    // Сохраняем message_id, чтобы при необходимости можно было удалить сообщение
                    $products[$newProductId]['message_id'] = $messageId;

                    // Сохраняем обновлённые продукты в кеш
                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

                    // Обновляем сообщение о продукте
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

    /**
     * Генерация (или уточнение) данных о продукте локально,
     * если не удалось получить информацию из API.
     */
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

        if ($productData) {
            $newNutritionalData = $this->parseNutritionalData($productData);

            if (isset($products[$productId]['product'])) {
                foreach ($newNutritionalData as $key => $value) {
                    $products[$productId]['product'][$key] = $value;
                }
            }

            // Помечаем продукт как "отредактированный"
            $products[$productId]['product']['edited'] = 1;
            // Обновляем название продукта
            $products[$productId]['product_translation']['name'] = $saidName;

            Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

            // Обновляем сообщение (карточку продукта)
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
                'show_alert'       => false,
            ]);
        }
    }

    /**
     * Разбор сгенерированной/полученной строки вида:
     *   "Калории - 100; Белки - 2; Жиры - 3; Углеводы - 10"
     */
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
                    case 'калории':
                        $nutritionalData['calories'] = (float) $value;
                        break;
                    case 'белки':
                        $nutritionalData['proteins'] = (float) $value;
                        break;
                    case 'жиры':
                        $nutritionalData['fats'] = (float) $value;
                        break;
                    case 'углеводы':
                        $nutritionalData['carbohydrates'] = (float) $value;
                        break;
                }
            }
        }

        return $nutritionalData;
    }
}
