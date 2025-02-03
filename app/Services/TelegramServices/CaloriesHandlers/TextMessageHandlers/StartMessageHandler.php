<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

class StartMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = $message->getText();

        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        $parts = explode(' ', $text);
        $code = $parts[1] ?? null;

        if ($code) {
            $result = $this->diaryApiService->checkTelegramCode($code, $chatId);

            if (!empty($result['success']) && $result['success'] === true) {

                //from calories

                $caloriesUserId = $result['user_id'];

                $botUser = Utilities::saveAndNotify(
                    $commonData['chatId'],
                    $commonData['firstName'],
                    $commonData['lastName'],
                    $commonData['username'],
                    $bot,
                    $commonData['premium'],
                    'calories',
                    $result,
                );

                $botUser->calories_id = $caloriesUserId;
                $botUser->save();

                Log::info("User {$chatId} linked with calories ID {$caloriesUserId}");

                $this->sendWelcome($bot, $telegram, $message, $commonData);
                return;
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => __('calories365-bot.invalid_or_used_code')
                ]);
                return;
            }
        }

        if ($botUser && $botUser->calories_id) {

            //already existed acc

            $this->sendWelcome($bot, $telegram, $message, $commonData);

        } else {

            //from bot
           Utilities::saveAndNotify(
                $commonData['chatId'],
                $commonData['firstName'],
                $commonData['lastName'],
                $commonData['username'],
                $bot,
                $commonData['premium'],
               'bot_link'
            );

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.seems_you_are_new')
            ]);
        }
    }

    protected function sendWelcome($bot, $telegram, $message, array $commonData): void
    {
        $imagePath = $bot->message_image;
        $messageText = __('calories365-bot.welcome_guide');

        $keyboard = Keyboard::make([
            'resize_keyboard' => true,
        ])
            ->row([
                ['text' => __('calories365-bot.menu')],
                ['text' => __('calories365-bot.statistics')]
            ])
            ->row([
                ['text' => __('calories365-bot.choose_language')],
                ['text' => __('calories365-bot.feedback')]
            ]);

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
            $telegram->sendMessage([
                'chat_id'      => $commonData['chatId'],
                'text'         => $messageText,
                'reply_markup' => $keyboard
            ]);
        }
    }
}
