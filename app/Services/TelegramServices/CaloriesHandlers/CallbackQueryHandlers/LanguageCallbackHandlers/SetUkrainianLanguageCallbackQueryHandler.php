<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers;

use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class SetUkrainianLanguageCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        if ($botUser) {
            $botUser->locale = 'ua';
            $botUser->save();
            App::setLocale('ua');
        }

        $messageId = $callbackQuery->getMessage()->getMessageId();
        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting language message: '.$e->getMessage());
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => 'Мова встановлена на українську',
            'show_alert' => true,
        ]);

        $keyboard = Keyboard::make([
            'resize_keyboard' => true,
        ])
            ->row([
                ['text' => __('calories365-bot.menu')],
                ['text' => __('calories365-bot.statistics')],
            ])
            ->row([
                ['text' => __('calories365-bot.choose_language')],
                ['text' => __('calories365-bot.feedback')],
            ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Мову встановлено на українську',
            'reply_markup' => $keyboard,
        ]);
    }
}
