<?php

namespace App\Services\TelegramServices\Middleware;

use App\Jobs\SaveAndNotifyJob;
use App\Jobs\UpdateLastActiveAtJob;
use App\Models\BotUser;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Objects\Update;

class CheckUserAuthAndLocale
{
    /**
     * @param  array  $passable  ['botTypeName','bot','telegram','update','excludedCommands', ...]
     * @return mixed
     */
    public function handle($passable, Closure $next)
    {
        $bot = $passable['bot'];
        $telegram = $passable['telegram'];
        /** @var Update $update */
        $update = $passable['update'];

        $excludedCommands = $passable['excludedCommands'] ?? [];
        $text = $update->getMessage()?->getText();
        $userId = $this->getUserId($update);
        $language = $this->getUserLanguage($update);

        // Initialize botUser to avoid undefined variable errors
        $botUser = null;

        //        $botUser = BotUser::where('telegram_id', $userId)->first();

        if ($userId) {
            $botUser = Cache::remember(
                'tg_bot_user_'.$userId,
                300,
                static function () use ($userId) {
                    return BotUser::select('id', 'telegram_id', 'calories_id', 'locale', 'premium')
                        ->where('telegram_id', $userId)
                        ->first();
                }
            );
        }
        $this->checkAndSetLocale($botUser, $language);

        if ($this->isExcludedCommand($text, $excludedCommands)) {
            $passable['botUser'] = $botUser;

            return $next($passable);
        }

        if (! $userId || ! $botUser || ! $botUser->calories_id) {
            $this->sendMustBeAuthorized($telegram, $userId);

            return ! $userId ? $next($passable) : null;
        }

        $passable['botUser'] = $botUser;

        $this->throttleLastActiveUpdate($botUser);

        $this->checkAndUpdateUserData($update, $botUser, $bot);

        return $next($passable);
    }

    private function getUserId(Update $update): ?int
    {
        return $update->getMessage()?->getChat()?->getId()
            ?: $update->getCallbackQuery()?->getChat()?->getId();
    }

    private function getUserLanguage(Update $update): ?string
    {
        return $update->getMessage()?->getFrom()?->getLanguageCode()
            ?: $update->getCallbackQuery()?->getFrom()?->getLanguageCode();
    }

    private function checkAndSetLocale(?BotUser $botUser, ?string $language): void
    {
        if ($botUser && $botUser->locale) {
            App::setLocale($botUser->locale);
        } else {
            if ($language) {
                if ($language === 'uk') {
                    $language = 'ua';
                }
                App::setLocale($language);
            }
        }
    }

    private function isExcludedCommand(?string $text, array $excludedCommands): bool
    {
        if (! $text) {
            return false;
        }

        foreach ($excludedCommands as $excluded) {
            if (str_starts_with($text, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private function sendMustBeAuthorized($telegram, ?int $userId): void
    {
        if ($userId) {
            $telegram->sendMessage([
                'chat_id' => $userId,
                'text' => __('calories365-bot.you_must_be_authorized'),
            ]);
        }
    }

    /**
     * Once every n minutes (for example, 5 minutes) we update last_active_at.
     */
    private function throttleLastActiveUpdate(BotUser $botUser, int $minutes = 5): void
    {
        $cacheKey = 'user_last_active_'.$botUser->id;
        $lastActive = Cache::get($cacheKey);

        $needToUpdate = false;
        if (! $lastActive) {
            $needToUpdate = true;
        } else {
            $diff = Carbon::parse($lastActive)->diffInMinutes(now());
            if ($diff >= $minutes) {
                $needToUpdate = true;
            }
        }

        if ($needToUpdate) {
            UpdateLastActiveAtJob::dispatch($botUser->id);

            Cache::put($cacheKey, now(), now()->addMinutes(5));
        }
    }

    /**
     *  Check for changes in name/username/premium and if necessary
     *  call SaveAndNotifyJob (but only if the data has changed).
     */
    private function checkAndUpdateUserData(Update $update, BotUser $botUser, $bot): void
    {

        if ($update->getCallbackQuery()) {
            return;
        }

        $firstName = $update->getMessage()?->getFrom()?->getFirstName()
            ?: $update->getCallbackQuery()?->getFrom()?->getFirstName();

        $lastName = $update->getMessage()?->getFrom()?->getLastName()
            ?: $update->getCallbackQuery()?->getFrom()?->getLastName();

        $username = $update->getMessage()?->getFrom()?->getUsername()
            ?: $update->getCallbackQuery()?->getFrom()?->getUsername();

        $premium = (bool) $botUser->premium;
        $userId = $botUser->telegram_id;

        $latestData = [
            'name' => trim($firstName.' '.$lastName),
            'username' => $username,
            'premium' => $premium,
        ];

        $cacheKey = 'bot_user_'.$botUser->id;
        $cachedData = Cache::get($cacheKey);

        if (! $cachedData || $cachedData != $latestData) {
            Cache::put($cacheKey, $latestData, now()->addDays(7));

            SaveAndNotifyJob::dispatch(
                $userId,
                $firstName,
                $lastName,
                $username,
                $bot,
                $premium
            );
        }
    }
}
