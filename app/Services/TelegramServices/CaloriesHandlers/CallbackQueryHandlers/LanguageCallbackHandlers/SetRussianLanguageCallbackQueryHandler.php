<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\LanguageCallbackHandlers;

use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;

class SetRussianLanguageCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();

        if ($botUser) {
            $botUser->locale = 'ru';
            $botUser->save();
            App::setLocale('ru');
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
            'text'    => 'Язык установлен на русский',
            'show_alert' => true
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
          'text' => 'Язык установлен на русский',
          'reply_markup' => $keyboard
      ]);
    }
}
