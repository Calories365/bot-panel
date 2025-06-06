<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\DiaryApiService;
use Telegram\Bot\Keyboard\Keyboard;

class StatsMessageHandler
{
    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {

        $chatId = $message->getChat()->getId();

        $inlineKeyboard = Keyboard::make([
            'inline_keyboard' => [
                [
                    [
                        'text' => __('calories365-bot.breakfast'),
                        'callback_data' => 'Breakfast',
                    ],
                    [
                        'text' => __('calories365-bot.lunch'),
                        'callback_data' => 'Dinner',
                    ],
                    [
                        'text' => __('calories365-bot.dinner'),
                        'callback_data' => 'Supper',
                    ],
                ],
                [
                    [
                        'text' => __('calories365-bot.whole_day'),
                        'callback_data' => 'AllDay',
                    ],
                ],
            ],
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.statistics'),
            'reply_markup' => $inlineKeyboard,
        ]);

    }
}
