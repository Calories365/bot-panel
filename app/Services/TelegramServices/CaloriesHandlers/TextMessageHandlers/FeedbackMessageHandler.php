<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;

class FeedbackMessageHandler
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message)
    {
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

            $telegram->sendMessage([
                'chat_id'      => $chatId,
                'text'         => __('calories365-bot.send_feedback_email'),
            ]);

            return;
        }
}
