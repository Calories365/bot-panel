<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Services\TelegramServices\BaseHandlers\TextMessageHandlers\Telegram;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class LanguageMessageHandler
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message)
    {
        $text = $message->getText();
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];
        $botUser = BotUser::where('telegram_id', $chatId)->first();

        if ($text === '/language') {
            $keyboard = Keyboard::make([
                'keyboard' => [
                    ['English', 'Русский', 'Українська'],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.please_choose_your_language'),
                'reply_markup' => $keyboard
            ]);
            return;
        }

        if ($text === 'English') {
            if ($botUser) {
                $botUser->locale = 'en';
                $botUser->save();
                App::setLocale('en');
            }
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Your language has been set to Ukrainian.'
            ]);
            return;
        }

        if ($text === 'Русский') {
            if ($botUser) {
                $botUser->locale = 'ru';
                $botUser->save();
                App::setLocale('ru');
            }
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Ваш язык установлен на русский.'
            ]);
            return;
        }

        if ($text === 'Українська') {
            if ($botUser) {
                $botUser->locale = 'ua';
                $botUser->save();
                App::setLocale('ua');
            }
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Вашу мову встановлено на українську.'
            ]);
            return;
        }
    }
}
