<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class LanguageMessageHandler
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message)
    {
        //for academic usage
        if ($bot->name === 'calories365KNU_bot'){
            $keyboard = [
                [
                    ['text' => 'Українська', 'callback_data' => 'Ukrainian'],
                    ['text' => 'English', 'callback_data' => 'English'],
                ],
            ];
        } else {
            $keyboard = [
                [
                    ['text' => 'Русский', 'callback_data' => 'Russian'],
                    ['text' => 'Українська', 'callback_data' => 'Ukrainian'],
                    ['text' => 'English', 'callback_data' => 'English'],
                ],
            ];
        }

        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];



            $inlineKeyboard = Keyboard::make([
                'inline_keyboard' => $keyboard
            ]);

            $telegram->sendMessage([
                'chat_id'      => $chatId,
                'text'         => __('calories365-bot.please_choose_your_language'),
                'reply_markup' => $inlineKeyboard
            ]);

            return;
        }
}
