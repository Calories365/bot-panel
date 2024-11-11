<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;


use App\Services\ChatGPTServices\SpeechToTextService;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateCallbackQueryHandler implements CallbackQueryHandlerInterface
{
     use EditHandlerTrait;
    protected SpeechToTextService $speechToTextService;
    public function __construct(SpeechToTextService $speechToTextService)
    {
        {
            $this->speechToTextService = $speechToTextService;
        }
    }

    public function handle($bot, $telegram, $callbackQuery)
    {
        $callbackData = $callbackQuery->getData();
        $parts = explode('_', $callbackData);

        if (isset($parts[1])) {
            $productId = $parts[1];

            $chatId = $callbackQuery->getMessage()->getChat()->getId();

            $userId = $callbackQuery->getFrom()->getId();

            $products = Cache::get("user_products_{$userId}", []);

            if (isset($products[$productId])) {

                $saidName = $products[$productId]['product_translation']['said_name'];

                try {

               $productData = $this->speechToTextService->generateNewProductData($saidName);

            } catch (GuzzleException $e) {
                    Log::error("Error generating product data: " . $e->getMessage());
                }

                if ($productData) {
                    $newNutritionalData = $this->parseNutritionalData($productData);

                    if (isset($products[$productId]['product'])) {
                        foreach ($newNutritionalData as $key => $value) {
                            $products[$productId]['product'][$key] = $value;
                        }
                    }

                    $products[$productId]['product']['edited'] = 1;

                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

                    $this->updateProductMessage($telegram, $chatId, $products[$productId]);

                } else {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQuery->getId(),
                        'text' => 'Произошла ошибка генерации данных.',
                        'show_alert' => false,
                    ]);
                }

                $telegram->answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->getId(),
                    'text' => 'Данные продукта обновлены.',
                    'show_alert' => false,
                ]);
            }
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
            if (count($keyValue) == 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);

                switch (mb_strtolower($key)) {
                    case 'калории':
                        $nutritionalData['calories'] = floatval($value);
                        break;
                    case 'белки':
                        $nutritionalData['proteins'] = floatval($value);
                        break;
                    case 'жиры':
                        $nutritionalData['fats'] = floatval($value);
                        break;
                    case 'углеводы':
                        $nutritionalData['carbohydrates'] = floatval($value);
                        break;
                }
            }
        }

        return $nutritionalData;
    }


}
