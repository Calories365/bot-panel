<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use Telegram\Bot\Keyboard\Keyboard;

class FontMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    public function handle($bot, $telegram, $message, $botUser)
    {
        $common = self::extractCommonData($message);
        $chatId = $common['chatId'];

        $inlineKeyboard = Keyboard::make([
            'inline_keyboard' => [
                [
                    ['text' => __('calories365-bot.yes'), 'callback_data' => 'bigfont_yes'],
                    ['text' => __('calories365-bot.no'),  'callback_data' => 'bigfont_no'],
                ],
            ],
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.big_font_question'),
            'reply_markup' => $inlineKeyboard,
        ]);
    }
}
