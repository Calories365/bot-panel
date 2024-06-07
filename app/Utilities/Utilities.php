<?php

namespace App\Utilities;

use App\Models\BotUser;

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

    public static function getParam($update): string
    {
        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
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
}
