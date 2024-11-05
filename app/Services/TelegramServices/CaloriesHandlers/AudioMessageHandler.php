<?php

namespace App\Services\TelegramServices\CaloriesHandlers;

use App\Services\AudioConversionService;
use App\Services\ChatGPTService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Cache; // Добавляем фасад Cache
use Illuminate\Support\Facades\Log;

class AudioMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

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

    public function handle($bot, $telegram, $message)
    {
        $commonData = self::extractCommonData($message);
        $userId = $commonData['userId'];

        $chatId = $commonData['chatId'];

        if (isset($message['voice'])) {

            $text = $this->audioConversionService->processAudioMessage($telegram, $bot, $message);

            if ($text) {
                Log::info('Product list: ' . $text);

                $responseArray = $this->diaryApiService->sendText($text);
                Log::info('Response from calories API: ' . print_r($responseArray, true));

                if (isset($responseArray['error'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Произошла ошибка: ' . $responseArray['error']
                    ]);
                    return;
                }

                if (isset($responseArray['message']) && $responseArray['message'] === 'Products found' && !empty($responseArray['products'])) {
                    $products = $responseArray['products'];

                    // Инициализируем массив для хранения продуктов с дополнительной информацией
                    $userProducts = [];

                    foreach ($products as $index => $productInfo) {

                        if (isset($productInfo['product_translation']) && isset($productInfo['product'])) {
                            $productTranslation = $productInfo['product_translation'];
                            $product = $productInfo['product'];

                            $messageText = "*" . $productTranslation['name'] . "*\n";
                            $messageText .= "Количество: " . ($product['quantity_grams'] ?? '—') . " грамм\n";
                            $messageText .= "Калории: " . ($product['calories'] ?? '—') . " ккал\n";
                            $messageText .= "Белки: " . ($product['proteins'] ?? '—') . " г\n";
                            $messageText .= "Углеводы: " . ($product['carbohydrates'] ?? '—') . " г\n";
                            $messageText .= "Жиры: " . ($product['fats'] ?? '—') . " г\n";

                            $productId = $productTranslation['id'];

                            $inlineKeyboard = [
                                [
                                    [
                                        'text' => 'Изменить',
                                        'callback_data' => 'edit_' . $productId
                                    ],
                                    [
                                        'text' => 'Удалить',
                                        'callback_data' => 'delete_' . $productId
                                    ]
                                ]
                            ];

                            $replyMarkup = json_encode([
                                'inline_keyboard' => $inlineKeyboard
                            ]);

                            // Отправляем сообщение пользователю и получаем отправленное сообщение
                            $sentMessage = $telegram->sendMessage([
                                'chat_id' => $chatId,
                                'text' => $messageText,
                                'parse_mode' => 'Markdown',
                                'reply_markup' => $replyMarkup
                            ]);

                            // Сохраняем информацию о продукте и message_id в массиве userProducts
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

                    // Сохраняем список продуктов в кеше с привязкой к userId
                    Cache::put("user_products_{$userId}", $userProducts, now()->addMinutes(30)); // Время хранения - 30 минут

                    // Отправляем сообщение с общими действиями
                    $finalMessageText = "Действия с продуктами:\n";

                    $finalInlineKeyboard = [
                        [
                            [
                                'text' => 'Сохранить',
                                'callback_data' => 'save'
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

                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => $finalMessageText,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $finalReplyMarkup
                    ]);
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
