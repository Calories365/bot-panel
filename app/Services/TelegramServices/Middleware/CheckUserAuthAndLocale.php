<?php

namespace App\Services\TelegramServices\Middleware;

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
