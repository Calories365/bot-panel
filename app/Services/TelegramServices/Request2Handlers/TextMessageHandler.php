<?php

namespace App\Services\TelegramServices\Request2Handlers;

use App\Services\TelegramServices\MessageHandlers\MessageHandlerInterface;
use App\Services\TelegramServices\MessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Log;

class TextMessageHandler implements MessageHandlerInterface
{

    use BasicDataExtractor;

    public function handle($bot, $telegram, $message)
    {
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
            Log::info('saveAndNotifyAllManagers');
            Utilities::saveAndNotifyAllManagers($commonData['chatId'], $commonData['firstName'], $commonData['lastName'], $commonData['username'], $bot, $commonData['premium'], $text);

            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => 'Данные приняты',
            ]);
        }
    }
}
