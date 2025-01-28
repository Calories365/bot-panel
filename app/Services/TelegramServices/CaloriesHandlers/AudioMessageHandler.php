<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Models\Subscription;
use App\Services\AudioConversionService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class AudioMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor, EditHandlerTrait;

    protected AudioConversionService $audioConversionService;
    protected DiaryApiService $diaryApiService;

    public function __construct(
        AudioConversionService $audioConversionService,
        DiaryApiService        $diaryApiService,
    )
    {
        $this->audioConversionService = $audioConversionService;
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $userId = $commonData['userId'];
        $chatId = $commonData['chatId'];

        if (isset($message['voice'])) {

            $subscription = Subscription::firstOrCreate(
                ['user_id' => $botUser->calories_id],
            );
            Log::info(print_r($subscription, true));
            if (!$subscription->canTranscribeAudio()) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.subscription_required_message')
                ]);
                return;
            }

            if (!$subscription->isPremium()) {
                    $subscription->incrementTranscribeCounter();
            }


            $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message);

            if ($text) {
                Log::info('Product list: ' . $text);

                $locale = $botUser->locale;
                $caloriesId = $botUser->calories_id;

                $responseArray = $this->diaryApiService->sendText($text, $caloriesId, $locale);

                if (isset($responseArray['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => __('calories365-bot.error_occurred') . $responseArray['error']
                    ]);
                    return;
                }

                if (isset($responseArray['message']) && $responseArray['message'] === 'Products found' && !empty($responseArray['products'])) {
                    $products = $responseArray['products'];

                    $userProducts = [];

                    foreach ($products as $index => $productInfo) {
                        if (isset($productInfo['product_translation']) && isset($productInfo['product'])) {
                            $productTranslation = $productInfo['product_translation'];
                            $product = $productInfo['product'];
                            $productId = $productTranslation['id'];
                            $this->generateTableBody($product, $productTranslation, $productId);

                            $sentMessage = $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $this->messageText,
                                'parse_mode' => 'Markdown',
                                'reply_markup' => $this->replyMarkup
                            ]);

                            $userProducts[$productId] = [
                                'product_translation' => $productTranslation,
                                'product' => $product,
                                'message_id' => $sentMessage->getMessageId()
                            ];
                        } else {
                            $messageText = ($index + 1) . ". " . __('calories365-bot.incomplete_product_info') . "\n";

                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $messageText,
                                'parse_mode' => 'Markdown'
                            ]);
                        }
                    }

                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30)); // Время хранения - 30 минут

                    $finalMessageText = __('calories365-bot.save_products_for') . "\n";

                    $finalInlineKeyboard = [
                        [
                            [
                                'text' => __('calories365-bot.breakfast'),
                                'callback_data' => 'save_morning'
                            ],
                            [
                                'text' => __('calories365-bot.lunch'),
                                'callback_data' => 'save_dinner'
                            ],
                        ],
                        [
                            [
                                'text' => __('calories365-bot.dinner'),
                                'callback_data' => 'save_supper'
                            ],
                            [
                                'text' => __('calories365-bot.cancel'),
                                'callback_data' => 'cancel'
                            ]
                        ]
                    ];

                    $finalReplyMarkup = json_encode([
                        'inline_keyboard' => $finalInlineKeyboard
                    ]);

                    $finalMessage = $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $finalMessageText,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $finalReplyMarkup
                    ]);
                    $finalMessageId = $finalMessage->getMessageId();

                    Cache::put("user_final_message_id_{$userId}", $finalMessageId, now()->addMinutes(30));
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $responseArray['message'] ?? __('calories365-bot.products_not_found')
                    ]);
                }
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.failed_to_recognize_audio_message')
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
}
