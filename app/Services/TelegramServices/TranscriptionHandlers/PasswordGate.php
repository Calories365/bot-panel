<?php

namespace App\Services\TelegramServices\TranscriptionHandlers;

use App\Models\BotAccess;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Illuminate\Support\Facades\Hash;
use Telegram\Bot\Objects\Message;

/**
 * The transcription bot works only for those who sent its password once.
 * Before that every message except /start is taken as an attempt at it.
 */
class PasswordGate
{
    use BasicDataExtractor;

    /**
     * Whether the message goes on to the bot; when it does not, the gate has
     * answered it.
     */
    public function passes($bot, $telegram, Message $message): bool
    {
        $text = trim((string) $message->getText());

        if (BotAccess::granted($bot->id, $message->getFrom()->getId()) || str_starts_with($text, '/start')) {
            return true;
        }

        $chatId = $message->getChat()->getId();

        if ($text === '') {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('transcription-bot.enter_password')]);

            return false;
        }

        if (! Hash::check($text, (string) config('services.transcription_bot.password_hash'))) {
            $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('transcription-bot.wrong_password')]);

            return false;
        }

        $commonData = self::extractCommonData($message);
        Utilities::saveAndNotify(
            $commonData['chatId'],
            $commonData['firstName'],
            $commonData['lastName'],
            $commonData['username'],
            $bot,
            $commonData['premium']
        );
        BotAccess::firstOrCreate(['bot_id' => $bot->id, 'telegram_id' => $commonData['userId']]);

        $telegram->sendMessage(['chat_id' => $chatId, 'text' => __('transcription-bot.access_granted')]);

        return false;
    }
}
