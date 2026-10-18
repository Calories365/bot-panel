<?php

namespace App\Utilities;

use App\Models\BotUser;

class Utilities
{
    public static function saveAndNotify($chatId, $first_name, $lastName, $username, $bot, $premium, $source = null, $result = null, $locale = null)
    {
        $botUser = BotUser::addOrUpdateUser($chatId, $first_name, $lastName, $username, $bot->id, $premium, $source, $result, $locale);
        if ($botUser->wasRecentlyCreated) {
            $userMention = "[{$first_name}](tg://user?id={$chatId})";
            $adminMessage = $premium ? 'премиум ' : '';
            $messageText = "Новый {$adminMessage}пользователь: {$userMention}";
            $bot->notifyAdmins($messageText);
        }

        return $botUser;
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

    public static function generateTableType2($title, $dataArray)
    {
        $title = $title.": \n\n";

        $maxLenCol1 = 0;
        $maxLenCol2 = 0;

        foreach ($dataArray as $row) {
            $lenCol1 = mb_strwidth($row[0], 'UTF-8');
            $lenCol2 = mb_strwidth((string) $row[1], 'UTF-8');

            if ($lenCol1 > $maxLenCol1) {
                $maxLenCol1 = $lenCol1;
            }
            if ($lenCol2 > $maxLenCol2) {
                $maxLenCol2 = $lenCol2;
            }
        }

        $partition = '`|'
            .str_repeat('-', $maxLenCol1 + 2)
            .'|'
            .str_repeat('-', $maxLenCol2 + 2)
            ."|\n";

        $body = '';

        foreach ($dataArray as $row) {
            $cell1 = $row[0];
            $cell2 = (string) $row[1];

            $cell1Width = mb_strwidth($cell1, 'UTF-8');
            $cell2Width = mb_strwidth($cell2, 'UTF-8');

            $cell1 = ' '.$cell1.str_repeat(' ', ($maxLenCol1 - $cell1Width + 1));
            $cell2 = ' '.$cell2.str_repeat(' ', ($maxLenCol2 - $cell2Width + 1));

            $body .= "|{$cell1}|{$cell2}|\n";
        }

        $body .= '`';

        return $title.$partition.$body;
    }

    public static function generateTableType2ForBigFont($title, $dataArray)
    {
        $title = $title.":\n\n";

        $fmt = static function ($v): string {
            if (! is_numeric($v)) {
                return (string) $v;
            }
            $s = number_format((float) $v, 1, '.', '');

            return rtrim(rtrim($s, '0'), '.');
        };

        $lines = [];
        foreach ($dataArray as $row) {
            $name = (string) $row[0];
            $value = $fmt($row[1]);
            $lines[] = "• {$name}: {$value}";
        }

        return $title.implode("\n", $lines);
    }

    public static function applySynonyms(array &$handlers, array $synonyms): void
    {
        foreach ($synonyms as $mainKey => $words) {
            if (isset($handlers[$mainKey])) {
                $handler = $handlers[$mainKey];

                foreach ($words as $word) {
                    $handlers[$word] = $handler;
                }
            }
        }
    }
}
