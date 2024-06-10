<?php

namespace App\Services\TelegramServices\RequestHandlerService;

use App\Services\TelegramServices\DefaultHandlerParts\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;

class TextMessageHandler
{
    use BasicDataExtractor;

    public static function handle($bot, $telegram, $update)
    {
        $message = $update->getMessage();
        $text = $message->getText();
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];
        $cacheKey = "user_{$commonData['fromId']}_application";

        if (str_contains($text, '/start')) {
            Cache::forget($cacheKey);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Введите ваш возраст",
            ]);
            Cache::put($cacheKey, ['step' => 'age']);
        } else {
            $userData = Cache::get($cacheKey, []);
            if (isset($userData['step'])) {
                switch ($userData['step']) {
                    case 'age':
                        $userData['age'] = $text;
                        $userData['step'] = 'name';
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => "Введите ваше имя",
                        ]);
                        break;
                    case 'name':
                        $userData['name'] = $text;
                        $userData['step'] = 'contact';
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => "Введите ваш контактный номер",
                        ]);
                        break;
                    case 'contact':
                        $userData['contact'] = $text;
                        $managerMessage = "Возраст: {$userData['age']}\nИмя: {$userData['name']}\nКонтакт: {$userData['contact']}\n";
                        Utilities::saveAndNotifyManagers(
                            $commonData['chatId'],
                            $commonData['firstName'],
                            $commonData['lastName'],
                            $commonData['username'],
                            $bot,
                            $commonData['premium'],
                            $managerMessage
                        );
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => "Спасибо, ваша заявка принята!",
                        ]);
                        Cache::forget($cacheKey);
                        break;
                }
                Cache::put($cacheKey, $userData);
            }
        }
    }
}
