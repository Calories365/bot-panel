<?php

namespace App\Traits;
trait BasicDataExtractor
{
    public static function extractCommonData($message)
    {
        $from = $message->getFrom();
        $chat = $message->getChat();

        return [
            'chatId' => $chat->getId(),
            'firstName' => $chat->getFirstName(),
            'lastName' => $chat->getLastName(),
            'username' => $chat->getUsername(),
            'fromId' => $from->getId(),
            'premium' => $from->getIsPremium(),
        ];
    }
}
