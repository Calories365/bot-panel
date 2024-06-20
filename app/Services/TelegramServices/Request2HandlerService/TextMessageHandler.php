<?php

namespace App\Services\TelegramServices\Request2HandlerService;

use App\Services\TelegramServices\DefaultHandlerParts\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;

class TextMessageHandler
{
    use BasicDataExtractor;


    public static function handle($bot, $telegram, $update)
    {
        $message = $update->getMessage();
        $text = $message->getText();
        $commonData = self::extractCommonData($message);

        if (str_contains($text, 'start')) {
            $messageText = $bot->message;
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => $messageText,
            ]);

            Utilities::saveAndNotify($commonData['chatId'], $commonData['firstName'], $commonData['lastName'], $commonData['username'], $bot, $commonData['premium']);
            return true;
        } else {
            Utilities::saveAndNotifyAllManagers($commonData['chatId'], $commonData['firstName'], $commonData['lastName'], $commonData['username'], $bot, $commonData['premium'], $text);

            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Данные приняты',
            ]);
        }
    }
}
