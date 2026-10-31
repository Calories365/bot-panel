<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use Telegram\Bot\Keyboard\Keyboard;

class StatsMessageHandler implements MessageHandlerInterface
{
    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {

        $chatId = $message->getChat()->getId();

        $rows = [
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
        ];

        // The curve is a chart: it lives in the Mini App, a bot without one has no such button.
        if ($bot->web_app_url ?? null) {
            $rows[] = [[
                'text' => __('calories365-bot.insulin_chart'),
                'web_app' => ['url' => rtrim($bot->web_app_url, '/').'/insulin'],
            ]];
        }

        $inlineKeyboard = Keyboard::make(['inline_keyboard' => $rows]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.statistics'),
            'reply_markup' => $inlineKeyboard,
        ]);

    }
}
