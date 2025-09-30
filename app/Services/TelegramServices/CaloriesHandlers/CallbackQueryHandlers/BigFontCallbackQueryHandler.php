<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Log;

class BigFontCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = true;

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $data = $callbackQuery->getData();

        $parts = explode('_', $data);
        $choice = $parts[1] ?? null;

        if (! $botUser || ($choice !== 'yes' && $choice !== 'no')) {
            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
            ]);

            return;
        }

        try {
            $botUser->big_font = ($choice === 'yes');
            $botUser->save();
        } catch (\Throwable $e) {
            Log::error('Failed to save big_font: '.$e->getMessage());
        }

        try {
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting big font question message: '.$e->getMessage());
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => $choice === 'yes' ? __('calories365-bot.big_font_enabled') : __('calories365-bot.regular_font_selected'),
            'show_alert' => false,
        ]);
    }
}
