<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\AudioConversionService;
use App\Services\ChatGPTService;
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
    protected ChatGPTService $chatGPTService;

    public function __construct(
        AudioConversionService $audioConversionService,
        DiaryApiService        $diaryApiService,
        ChatGPTService         $chatGPTService
    )
    {
        $this->audioConversionService = $audioConversionService;
        $this->diaryApiService = $diaryApiService;
        $this->chatGPTService = $chatGPTService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $commonData = self::extractCommonData($message);
        $userId = $commonData['userId'];

        $chatId = $commonData['chatId'];

        if (isset($message['voice'])) {

            $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message);

            if ($text) {
                Log::info('Product list: ' . $text);

                $locale = $botUser->locale;
                $caloriesId = $botUser->calories_id;

                $responseArray = $this->diaryApiService->sendText($text, $caloriesId, $locale);

                if (isset($responseArray['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Произошла ошибка: ' . $responseArray['error']
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
                            $messageText = ($index + 1) . ". Информация о продукте неполная.\n";

                            $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $messageText,
                                'parse_mode' => 'Markdown'
                            ]);
                        }
                    }
                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30)); // Время хранения - 30 минут

                    $finalMessageText = "Сохранить продукты на:\n";

                    $finalInlineKeyboard = [
                        [
                            [
                                'text' => 'Завтрак',
                                'callback_data' => 'save_morning'
                            ],
                            [
                                'text' => 'Oбед',
                                'callback_data' => 'save_dinner'
                            ],
                        ],
                        [
                            [
                                'text' => 'Ужин',
                                'callback_data' => 'save_supper'
                            ],
                            [
                                'text' => 'Отменить',
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
                        'text' => $responseArray['message'] ?? 'Продукты не найдены.'
                    ]);
                }

            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Не удалось распознать аудио сообщение.'
                ]);
            }
        } else {
            $text = $message->getText() ?: 'Получено не аудио сообщение.';
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }
    }
}
