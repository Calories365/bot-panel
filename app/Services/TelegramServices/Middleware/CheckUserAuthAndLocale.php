<?php

namespace App\Services\TelegramServices\Middleware;

use App\Jobs\SaveAndNotifyJob;
use App\Jobs\UpdateLastActiveAtJob;
use App\Utilities\Utilities;
use Closure;
use Illuminate\Support\Facades\Log;
use App\Models\BotUser;
use Illuminate\Support\Facades\App;
use Telegram\Bot\Objects\Update;

class CheckUserAuthAndLocale
{
    /**
     * @param array   $passable ['botTypeName','bot','telegram','update','excludedCommands', ...]
     * @param Closure $next
     * @return mixed
     */
    public function handle($passable, Closure $next)
    {
        $bot      = $passable['bot'];
        $telegram = $passable['telegram'];
        /** @var Update $update */
        $update   = $passable['update'];

        $excludedCommands = $passable['excludedCommands'] ?? [];

        $text = $update->getMessage()?->getText();

        $userId = $update->getMessage()?->getChat()?->getId()
            ?: $update->getCallbackQuery()?->getChat()?->getId();

        $language = $update->getMessage()?->getFrom()?->getLanguageCode()
            ?: $update->getCallbackQuery()?->getFrom()?->getLanguageCode();

        $botUser = BotUser::where('telegram_id', $userId)->first();

        if ($botUser && $botUser->locale) {
            App::setLocale($botUser->locale);

            UpdateLastActiveAtJob::dispatch($botUser->id);

            $chatId    = $userId;
            $firstName = $update->getMessage()?->getFrom()?->getFirstName()
                ?: $update->getCallbackQuery()?->getFrom()?->getFirstName();

            $lastName = $update->getMessage()?->getFrom()?->getLastName()
                ?: $update->getCallbackQuery()?->getFrom()?->getLastName();

            $username = $update->getMessage()?->getFrom()?->getUsername()
                ?: $update->getCallbackQuery()?->getFrom()?->getUsername();

            $bot->id = 5;

            $premium = (bool)$botUser->premium;

            SaveAndNotifyJob::dispatch($chatId, $firstName, $lastName, $username, $bot, $premium);

        } else {
            if ($language) {
                if ($language == 'uk') {
                    $language = 'ua';
                }
                App::setLocale($language);
            }
        }


        foreach ($excludedCommands as $excluded) {
            if (str_starts_with($text, $excluded)) {
                $passable['botUser'] = $botUser;
                return $next($passable);
            }
        }

        if (!$userId) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text'    => __('calories365-bot.you_must_be_authorized')
            ]);
            return $next($passable);
        }

        if (!$botUser) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text'    => __('calories365-bot.you_must_be_authorized'),
            ]);
            return null;
        }

        if (!$botUser->calories_id) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text'    => __('calories365-bot.you_must_be_authorized'),
            ]);
            return null;
        }

        $passable['botUser'] = $botUser;

        return $next($passable);
    }
}
