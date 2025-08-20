<?php

namespace App\Services\TelegramServices\ApprovalHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use App\Traits\ContactDataExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContactMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor, ContactDataExtractor;

    public function handle($bot, $telegram, $message, $botUser)
    {

        $commonData = self::extractCommonData($message);
        $contactData = self::extractContactData($message);

        if (! $contactData) {
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Ошибка, попробуйте перейти по ссылке еще раз!',
            ]);

            return;
        }

        $user = $botUser;
        if ($user) {
            $user->phone = $contactData['phoneNumber'];
            $user->save();
        }

        $userIdFromWordpress = Cache::get($commonData['chatId']);
        if (! $userIdFromWordpress) {
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Ошибка, попробуйте перейти по ссылке еще раз!',
            ]);

            return;
        }

        $phoneNumber = preg_replace('/[^0-9]/', '', $contactData['phoneNumber']);

        $data = [
            'wp_id' => $userIdFromWordpress,
            'tg_id' => $commonData['chatId'],
            'tg_username' => $commonData['username'],
            'tg_number' => $phoneNumber,
        ];

        try {
            $url = $bot->wordpress_endpoint;
            //            $response = Http::asForm()->post($url, $data);
            //            $body = $response->body();

            $body = '/ID already exists/';

            $patterns = [
                '/Wrong Query/' => 'Ошибка, попробуйте перейти по ссылке еще раз!',
                '/Number already exists/' => 'Ваш номер уже есть в базе!',
                '/Success/' => 'Успех!',
                '/User does not exist/' => 'Такого пользователя не существует!',
                '/Number code invalid/' => 'Недопустимый код номера!',
                '/ID already exists/' => 'Ваш ID уже есть в базе!',
            ];

            foreach ($patterns as $pattern => $message) {
                if (preg_match($pattern, $body)) {
                    $telegram->sendMessage([
                        'chat_id' => $commonData['chatId'],
                        'text' => $message,
                    ]);
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error accessing /test.wp: '.$e->getMessage());
        }
    }
}
