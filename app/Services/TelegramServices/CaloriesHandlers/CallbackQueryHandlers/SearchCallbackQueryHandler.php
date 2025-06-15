<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use App\Services\ChatGPTServices\SpeechToTextService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\CaloriesHandlers\EditHandlerTrait;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    use EditHandlerTrait;

    protected DiaryApiService     $diaryApiService;
    protected SpeechToTextService $speechToTextService;

    public function __construct(
        DiaryApiService     $diaryApiService,
        SpeechToTextService $speechToTextService
    ) {
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

        if (!isset($parts[1])) {
            return;
        }

        $productId = $parts[1];
        $chatId    = $callbackQuery->getMessage()->getChat()->getId();
        $userId    = $callbackQuery->getFrom()->getId();

        $products = Cache::get("user_products_{$userId}", []);

        if (!isset($products[$productId])) {
            return;
        }

        $clickCount = Cache::increment("product_click_count_{$userId}_{$productId}");
        Cache::put("product_click_count_{$userId}_{$productId}", $clickCount, now()->addMinutes(30));

        $saidName       = $products[$productId]['product_translation']['said_name'];
        $quantityGrams  = $products[$productId]['product']['quantity_grams'] ?? '';
        $originalName   = $products[$productId]['product_translation']['original_name'] ?? '';
        $formattedText  = $saidName.' - '.$quantityGrams.' '.__('calories365-bot.grams');

        try {
            if ($clickCount > 1) {
                $this->updateViaAi($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
            } else {
                if ($saidName !== $originalName) {
                    $resp = $this->diaryApiService->getTheMostRelevantProduct($formattedText, $calories_id, $locale);
                    $product = $resp['product'] ?? null;

                    if (!$product) {
                        $this->updateViaAi($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                    }
                } else {
                    $this->updateViaAi($products, $productId, $userId, $telegram, $chatId, $callbackQuery);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SearchCallbackQueryHandler error: '.$e->getMessage());
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'              => __('calories365-bot.error_processing_data'),
                'show_alert'        => false,
            ]);
        }
    }

    private function updateViaAi(&$products, $productId, $userId, $telegram, $chatId, $callbackQuery): void
    {
        $saidName = $products[$productId]['product_translation']['said_name'];

        try {
            $raw =  $this->speechToTextService->generateNewProductData($saidName);
        } catch (\Throwable $e) {
            Log::error('aiGenerateProduct error: '.$e->getMessage());
        }

        if (!$raw || preg_match('/(sorry|извин|вибач|cannot|не могу|не можу|ошиб|error|помил)/iu', $raw)) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'              => __('calories365-bot.cannot_generate_product_data'),
                'show_alert'        => true,
            ]);
            return;
        }

        $nutritional = Utilities::parseAIGeneratedNutritionalData($raw);
        if (empty($nutritional['calories'] ?? null)) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text'              => __('calories365-bot.cannot_generate_product_data'),
                'show_alert'        => true,
            ]);
            return;
        }

        foreach ($nutritional as $k => $v) {
            $products[$productId]['product'][$k] = $v;
        }
        $products[$productId]['product_translation']['name'] = $saidName;

        Cache::put("user_products_{$userId}", $products, now()->addMinutes(30));
        $this->updateProductMessage($telegram, $chatId, $products[$productId]);

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text'              => __('calories365-bot.product_data_updated'),
            'show_alert'        => false,
        ]);
    }
}
