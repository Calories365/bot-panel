<?php

namespace App\Services\TelegramServices\ApprovalHandlerParts;

use App\Services\TelegramServices\DefaultHandlerParts\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;

class TextMessageHandler
{
    use BasicDataExtractor;

    public static function handleTextMessage($bot, $telegram, $update): void
    {
        $message = $update->getMessage();

        $commonData = self::extractCommonData($message);
        $text = $message->getText();

        $isPhone = Utilities::isPhoneNumber($text);

        if ($isPhone) {
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Для подтверждения номера надо нажать на кнопку "Поделится контактом"!',
            ]);
            return;
        }

        $userIdFromWordpress = Utilities::getParam($update) ?? '';

        if (!$userIdFromWordpress) {
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Ошибка, попробуйте перейти по ссылке еще раз!',
            ]);
            return;
        }

        Cache::put($commonData['chatId'], $userIdFromWordpress, 60);

        if (str_contains($text, '/start')) {
            $keyboard = Keyboard::make([
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])->row([
                [
                    'text' => 'Поделиться контактом',
                    'request_contact' => true
                ]
            ]);

            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Пожалуйста, поделитесь вашим контактом.',
                'reply_markup' => $keyboard
            ]);
        }
    }
}
