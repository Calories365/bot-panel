<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Models\Subscription;
use App\Services\AudioConversionService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AudioMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor, EditHandlerTrait;

    protected AudioConversionService $audioConversionService;

    protected DiaryApiService $diaryApiService;

    public function __construct(
        AudioConversionService $audioConversionService,
        DiaryApiService $diaryApiService,
    ) {
        $this->audioConversionService = $audioConversionService;
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $userId = $commonData['userId'];
        $chatId = $commonData['chatId'];

        if (isset($message['voice'])) {

            // for academic usage
            if ($bot->name != 'calories365KNU_bot') {

                $subscription = Subscription::firstOrCreate(
                    ['user_id' => $botUser->calories_id],
                );
                if (! $subscription->canTranscribeAudio()) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => __('calories365-bot.subscription_required_message'),
                    ]);

                    return;
                }

                if (! $subscription->isPremium()) {
                    $subscription->incrementTranscribeCounter();
                }
            }

            $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message);

            Log::info(print_r($text, true));

            if ($text) {

                $locale = $botUser->locale;
                $caloriesId = $botUser->calories_id;

                $responseArray = $this->diaryApiService->sendText($text, $caloriesId, $locale);

                Log::info(print_r($responseArray, true));

                if (isset($responseArray['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => __('calories365-bot.error_occurred').$responseArray['error'],
                    ]);

                    return;
                }

                if (isset($responseArray['message']) && $responseArray['message'] === 'Products found' && ! empty($responseArray['products'])) {
                    $products = $responseArray['products'];

                    $userProducts = [];

                    foreach ($products as $index => $productInfo) {
                        if (isset($productInfo['product_translation']) && isset($productInfo['product'])) {
                            $productTranslation = $productInfo['product_translation'];
                            $product = $productInfo['product'];
                            $productId = $productTranslation['id'];
                            Log::info('table body');
                            Log::info(print_r($product, true));
                            Log::info(print_r($productTranslation, true));
                            Log::info(print_r($productId, true));
                            $this->generateTableBody($product, $productTranslation, $productId);

                            $sentMessage = $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $this->messageText,
                                'parse_mode' => 'Markdown',
                                'reply_markup' => $this->replyMarkup,
                            ]);

                            $userProducts[$productId] = [
                                'product_translation' => $productTranslation,
                                'product' => $product,
                                'message_id' => $sentMessage->getMessageId(),
                            ];
                        } else {
                            $addedProduct = $this->generateProduct();
                            $this->generateTableBody($addedProduct['product'], $addedProduct['productTranslation'], $addedProduct['productId']);

                            $sentMessage = $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $this->messageText,
                                'parse_mode' => 'Markdown',
                                'reply_markup' => $this->replyMarkup,
                            ]);

                            $userProducts[$addedProduct['productId']] = [
                                'product_translation' => $addedProduct['productTranslation'],
                                'product' => $addedProduct['product'],
                                'message_id' => $sentMessage->getMessageId(),
                            ];
                        }
                    }

                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30));

                    $finalMessageText = __('calories365-bot.save_products_for')."\n";

                    $finalInlineKeyboard = [
                        [
                            [
                                'text' => __('calories365-bot.breakfast'),
                                'callback_data' => 'save_morning',
                            ],
                            [
                                'text' => __('calories365-bot.lunch'),
                                'callback_data' => 'save_dinner',
                            ],
                        ],
                        [
                            [
                                'text' => __('calories365-bot.dinner'),
                                'callback_data' => 'save_supper',
                            ],
                            [
                                'text' => __('calories365-bot.cancel'),
                                'callback_data' => 'cancel',
                            ],
                        ],
                    ];

                    $finalReplyMarkup = json_encode([
                        'inline_keyboard' => $finalInlineKeyboard,
                    ]);

                    $finalMessage = $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $finalMessageText,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $finalReplyMarkup,
                    ]);
                    $finalMessageId = $finalMessage->getMessageId();

                    Cache::put("user_final_message_id_{$userId}", $finalMessageId, now()->addMinutes(30));
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $responseArray['message'] ?? __('calories365-bot.products_not_found'),
                    ]);
                }
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.failed_to_recognize_audio_message'),
                ]);
            }
        } else {
            $text = $message->getText() ?: __('calories365-bot.not_an_audio_message_received');
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }
    }

    /**
     * Возвращает тестовый $productInfo с захардкоженными значениями.
     */
    private function generateProduct(): array
    {
        return [
            'productTranslation' => [
                'id'            => 77777777,
                'product_id'    => 77777777,
                'locale'        => 'ru',
                'name'          => 'Тварог',
                'said_name'     => 'Тварог',
                'original_name' => 'Тварог',
            ],

            'product' => [
                'id'              => 8265,
                'user_id'         => 89,
                'calories'        => 136,
                'proteins'        => 21,
                'carbohydrates'   => 3,
                'fats'            => 4,
                'fibers'          => 0,
                'quantity_grams'  => 200,

                'edited'          => 1,
                'verified'        => 1,
                'ai_generated'    => true,
            ],

            'productId' => 8265,
        ];
    }

}
