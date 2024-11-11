<?php

namespace App\Utilities;

use App\Models\BotUser;
use Illuminate\Support\Facades\Log;

class Utilities
{
    public static function saveAndNotify($chatId, $first_name, $lastName, $username, $bot, $premium): bool
    {
        BotUser::addOrUpdateUser($chatId, $first_name, $lastName, $username, $bot->id, $premium);
        $userMention = "[{$first_name}](tg://user?id=$chatId)";
        $adminMessage = $premium ? 'премиум ' : '';
        $messageText = "Новый {$adminMessage}пользователь: {$userMention}";
        $bot->notifyAdmins($messageText);
        return true;
    }

    public static function saveAndNotifyManagers($chatId, $first_name, $lastName, $username, $bot, $premium, $text): bool
    {
        BotUser::addOrUpdateUser($chatId, $first_name, $lastName, $username, $bot->id, $premium);

        $userMention = "[{$first_name}](tg://user?id=$chatId)";
        $adminMessage = $text;
        $messageText = "{$adminMessage} пользователь: {$userMention}";

        $bot->notifyManagers($bot, $messageText);
        return true;
    }

    public static function saveAndNotifyAllManagers($chatId, $first_name, $lastName, $username, $bot, $premium, $text): bool
    {
        BotUser::addOrUpdateUser($chatId, $first_name, $lastName, $username, $bot->id, $premium);

        $userMention = "[{$first_name}](tg://user?id=$chatId)";
        $adminMessage = $text;
        $messageText = "Сообщение: {$adminMessage} пользователь: {$userMention}";

        Log::info('notifyAllManagers');
        $bot->notifyAllManagers($bot, $messageText);
        return true;
    }

    public static function getParam($message): string
    {
        if (isset($message['text'])) {
            $text = $message['text'];
            if (preg_match('/\/start (\d+)/', $text, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    public static function isPhoneNumber($text): bool
    {
        $pattern = '/^\+?[0-9]+$/';
        if (preg_match($pattern, $text)) {
            return true;
        } else {
            return false;
        }
    }

    public static function generateTable($title, $quantity, $dataArray, $saidProduct)
    {
        $youSaid = 'Вы сказали: ' . $saidProduct . "\n\n";
        $title = $title . "\n\n";
        $quantity .="г";
        $quantity = str_pad(' ' . $quantity, 8, " ", STR_PAD_RIGHT);
        $header = "`| Параметр   | 100г  |" . $quantity. "|\n";
        $partition = "|------------|-------|-------|\n";
        $body = "";
        foreach ($dataArray as $key => $subArray) {
            $body .=
                "|". str_pad(' ' . $subArray[0], mb_strlen($subArray[0], "UTF-8")+12, " ", STR_PAD_RIGHT) .
                "|". str_pad(' ' . $subArray[1], 7, " ", STR_PAD_RIGHT) .
                "|". str_pad(' ' . $subArray[2], 7, " ", STR_PAD_RIGHT) . "|\n";
        }
        $body .= "`";
        return $youSaid . $title . $header . $partition . $body;
    }
}
