<?php

namespace App\Traits;
trait ContactDataExtractor
{
    public static function extractContactData($message)
    {
        $contact = $message->getContact();
        if ($contact) {
            return [
                'phoneNumber' => $contact->getPhoneNumber(),
                'telegramId' => $contact->getUserId(),
            ];
        }
        return [];
    }
}


