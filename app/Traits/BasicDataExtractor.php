<?php

namespace App\Traits;

trait BasicDataExtractor
{
    public static function extractCommonData($message)
    {
        $from = $message->getFrom();
        $chat = $message->getChat();
        $locale = $message->getFrom()?->getLanguageCode();

        return [
            'chatId' => $chat->getId(),
            'firstName' => $chat->getFirstName(),
            'lastName' => $chat->getLastName(),
            'username' => $chat->getUsername(),
            'fromId' => $from->getId(),
            'premium' => $from->getIsPremium(),
            'userId' => $from->getId(),
            'locale' => $locale,
        ];
    }
}
