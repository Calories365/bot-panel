<?php

namespace App\Services\TelegramServices\RequestHandlerService;

use App\Services\TelegramServices\DefaultHandlerParts\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;

class TextMessageHandler
{
    use BasicDataExtractor;

    public static function handle($bot, $telegram, $update): void
    {
        $message = $update->getMessage();
        $text = $message->getText();
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];
        $cacheKey = "user_{$commonData['fromId']}_application";

        $userData = Cache::get($cacheKey, []);

        if (empty($userData)) {
            if (str_contains($text, '/start')) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Введите ваш возраст",
                ]);
                Cache::put($cacheKey, ['step' => 'age']);
            } else {
                $telegram->sendMessage([
                'chat_id' => $chatId,
                    'text' => "Для начала формирования заявки нажмите /start",
                ]);
            }
        } else {
            switch ($userData['step']) {
                case 'age':
                    $userData['age'] = $text;
                    $userData['step'] = 'name';
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Введите ваше имя",
                    ]);
                    Cache::put($cacheKey, $userData);
                    break;
                case 'name':
                    $userData['name'] = $text;
                    $userData['step'] = 'contact';
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Введите ваш контактный номер",
                    ]);
                    Cache::put($cacheKey, $userData);
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
        }
    }
}
