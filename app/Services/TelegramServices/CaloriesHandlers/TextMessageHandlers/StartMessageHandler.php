<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\App;
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
        $codePart = $parts[1] ?? null;

        $code = null;
        $locale = null;

        if ($codePart) {
            $codeAndLang = explode('_', $codePart);
            $code = $codeAndLang[0] ?? null;
            $locale = $codeAndLang[1] ?? $commonData['locale'];

            if ($locale) {
                App::setLocale($locale);
            }

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
                    $locale
                );

                $botUser->calories_id = $caloriesUserId;
                $botUser->locale = $locale;
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
               'bot_link',
               null,
               $commonData['locale'],
            );

            App::setLocale($commonData['locale']);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => __('calories365-bot.seems_you_are_new', ['lang' => App::getLocale()])
            ]);
        }
    }

    protected function sendWelcome($bot, $telegram, $message, array $commonData): void
    {
        // Текст сообщения (как было ранее)
        if ($bot->name === 'calories365KNU_bot') {
            $messageText = __('calories365-bot.welcome_guide_KNU');
        } else {
            $messageText = __('calories365-bot.welcome_guide');
        }

        // Клавиатура (как у вас было)
        $keyboard = Keyboard::make(['resize_keyboard' => true])
            ->row([
                ['text' => __('calories365-bot.menu')],
                ['text' => __('calories365-bot.statistics')]
            ])
            ->row([
                ['text' => __('calories365-bot.choose_language')],
                ['text' => __('calories365-bot.feedback')]
            ]);

        // Получаем локаль
        $locale = App::getLocale();

        // Из таблицы bots берём путь к нужному видео для текущей локали
        // Предполагаем, что $bot является Eloquent-моделью с полями video_ru, video_ua, video_eng
        switch ($locale) {
            case 'ru':
                $videoPathFromDb = $bot->video_ru;
                break;
            case 'ua':
                $videoPathFromDb = $bot->video_ua;
                break;
            default:
                $videoPathFromDb = $bot->video_eng;
                break;
        }

        // Если в базе для этой локали есть путь к видео, пытаемся отправить именно видео
        if ($videoPathFromDb) {
            // Преобразуем /images/... в public/bots/... (аналогично тому, что делали с картинкой)
            $relativeVideoPath = str_replace('/images', 'public/bots', parse_url($videoPathFromDb, PHP_URL_PATH));

            if (Storage::exists($relativeVideoPath)) {
                $absoluteVideoPath = Storage::path($relativeVideoPath);
                $video = InputFile::create($absoluteVideoPath, basename($absoluteVideoPath));

                try {
                    $telegram->sendVideo([
                        'chat_id'      => $commonData['chatId'],
                        'video'        => $video,
                        'caption'      => $messageText,
                        'reply_markup' => $keyboard,
                    ]);
                    return; // Завершаем метод, раз видео уже отправили
                } catch (\Telegram\Bot\Exceptions\TelegramOtherException $e) {
                    // Обработка ошибок (заблокировал бота и т.д.)
                    if ($e->getMessage() === 'Forbidden: bot was blocked by the user') {
                        $userModel = BotUser::where('telegram_id', $commonData['chatId'])->firstOrFail();
                        $userModel->is_banned = 1;
                        $userModel->save();
                    } else {
                        Log::info($e->getMessage());
                    }
                }
            } else {
                Log::error("Video file not found: " . $relativeVideoPath);
            }
        }

        // Если не было видео или отправка видео не удалась – проверяем, есть ли у нас картинка
        $imagePath = $bot->message_image;
        if ($imagePath) {
            $relativeImagePath = str_replace('/images', 'public/bots', parse_url($imagePath, PHP_URL_PATH));
            if (Storage::exists($relativeImagePath)) {
                $absoluteImagePath = Storage::path($relativeImagePath);
                $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                try {
                    $telegram->sendPhoto([
                        'chat_id'      => $commonData['chatId'],
                        'photo'        => $photo,
                        'caption'      => $messageText,
                        'reply_markup' => $keyboard
                    ]);
                    return;
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
        }

        // Если ни видео, ни картинки нет – просто отправляем текст
        $telegram->sendMessage([
            'chat_id'      => $commonData['chatId'],
            'text'         => $messageText,
            'reply_markup' => $keyboard
        ]);
    }

}
