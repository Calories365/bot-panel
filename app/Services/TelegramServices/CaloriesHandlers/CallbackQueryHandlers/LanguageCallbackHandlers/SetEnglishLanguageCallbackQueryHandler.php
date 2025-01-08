<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers;

use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class SetEnglishLanguageCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $userId = $callbackQuery->getFrom()->getId();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        if ($botUser) {
            $botUser->locale = 'en';
            $botUser->save();
            App::setLocale('en');
        }

        $messageId = $callbackQuery->getMessage()->getMessageId();
        try {
            $telegram->deleteMessage([
                'chat_id'    => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting language message: " . $e->getMessage());
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);

        $keyboard = Keyboard::make([
            'resize_keyboard' => true,
        ])
            ->row([
                ['text' => __('calories365-bot.menu')],
                ['text' => __('calories365-bot.statistics')]
            ])
            ->row([
                ['text' => __('calories365-bot.choose_language')],
                ['text' => __('calories365-bot.feedback')]
            ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Language set to English',
            'reply_markup' => $keyboard
        ]);
    }
}
