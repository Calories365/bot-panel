<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

class StartMessageHandler
{
    use BasicDataExtractor;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message)
    {
        $text = $message->getText();

        if (!str_starts_with($text, '/start')) {
            return;
        }

        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        $botUser = Utilities::hasCaloriesId($chatId);

//        if (!$botUser){
//            $telegram->sendMessage([
//                'chat_id' => $chatId,
//                'text'    => "Вы должны быть авторизированны!"
//            ]);
//            return;
//        }


        $parts = explode(' ', $text);
        $code = $parts[1] ?? null;

        if ($code) {
            $result = $this->diaryApiService->checkTelegramCode($code, $chatId);

            if (!empty($result['success']) && $result['success'] === true) {
                $caloriesUserId = $result['user_id'];

                $botUser = Utilities::saveAndNotify(
                    $commonData['chatId'],
                    $commonData['firstName'],
                    $commonData['lastName'],
                    $commonData['username'],
                    $bot,
                    $commonData['premium']
                );

                $botUser->calories_id = $caloriesUserId;
                $botUser->save();

                Log::info("User {$chatId} linked with calories ID {$caloriesUserId}");

                $this->sendWelcome($bot, $telegram, $message, $commonData);
                return;
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => "Код недействителен или уже использован. Пожалуйста, зарегистрируйтесь заново."
                ]);
                return;
            }
        }

        if ($botUser) {
            $this->sendWelcome($bot, $telegram, $message, $commonData);

            Utilities::saveAndNotify(
                $commonData['chatId'],
                $commonData['firstName'],
                $commonData['lastName'],
                $commonData['username'],
                $bot,
                $commonData['premium']
            );
        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Похоже, вы здесь впервые. Чтобы связать аккаунт, используйте ссылку «Подключить» из личного кабинета (на сайте)."
            ]);
        }
    }

    /**
     * Отправка «приветственного» сообщения (с картинкой/клавиатурой).
     * Вынесена в отдельный метод, чтобы переиспользовать в разных условиях.
     */
    protected function sendWelcome($bot, $telegram, $message, array $commonData): void
    {
        $imagePath = $bot->message_image;
        $messageText = $bot->message;

        // Формируем клавиатуру
        $keyboard = Keyboard::make([
            'resize_keyboard' => true,
        ])->row([
            ['text' => '/stats_morning'],
            ['text' => '/stats_dinner']
        ])->row([
            ['text' => '/stats_supper'],
            ['text' => '/stats']
        ]);

        // Если есть картинка
        if ($imagePath) {
            $relativeImagePath = str_replace('/images', 'public/bots', parse_url($imagePath, PHP_URL_PATH));
            if (Storage::exists($relativeImagePath)) {
                $absoluteImagePath = Storage::path($relativeImagePath);
                $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                try {
                    $telegram->sendPhoto([
                        'chat_id' => $commonData['chatId'],
                        'photo'    => $photo,
                        'caption'  => $messageText,
                        'reply_markup' => $keyboard
                    ]);
                } catch (\Telegram\Bot\Exceptions\TelegramOtherException $e) {
                    // Если бот заблокирован
                    if ($e->getMessage() === 'Forbidden: bot was blocked by the user') {
                        $userModel = BotUser::where('telegram_id', $commonData['chatId'])->firstOrFail();
                        $userModel->is_banned = 1;
                        $userModel->save();
                    } else {
                        Log::info($e->getMessage());
                    }
                }
            } else {
                Log::error("Image file not found: " . $relativeImagePath);
            }
        } else {
            // Если картинки нет, отправляем обычное сообщение
            $telegram->sendMessage([
                'chat_id'      => $commonData['chatId'],
                'text'         => $messageText,
                'reply_markup' => $keyboard
            ]);
        }
    }
}

