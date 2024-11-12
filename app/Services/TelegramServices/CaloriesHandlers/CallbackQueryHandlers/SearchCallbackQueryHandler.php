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
    public function __construct(DiaryApiService $diaryApiService)
    {
        {
            $this->diaryApiService = $diaryApiService;
        }
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

                $saidName = $products[$productId]['product_translation']['said_name'];

                try {
                    $quantityGrams = $products[$productId]['product']['quantity_grams'];
                    $formatedText = $saidName . " - " . $quantityGrams . "грамм;";

                    $responseArray = $this->diaryApiService->sendText($formatedText);

                    $product = $responseArray['products'][0];

            } catch (GuzzleException $e) {
                    Log::error("Error generating product data: " . $e->getMessage());
                }

                if ($product) {

                    $products[$productId] = $product;
                    $products[$productId]['message_id'] = $messageId;

                    Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));

                    $products = Cache::get("user_products_{$userId}", []);

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
}
