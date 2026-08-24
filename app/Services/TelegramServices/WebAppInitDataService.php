<?php

namespace App\Services\TelegramServices;

use App\Models\Bot;
use Illuminate\Support\Facades\Cache;

/**
 * Validates Telegram Mini App initData against bot tokens stored in the panel.
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class WebAppInitDataService
{
    /**
     * How long initData stays acceptable, in seconds.
     */
    public const MAX_AGE = 86400;

    /**
     * Find the bot whose token signs this initData and return the parsed payload.
     *
     * @return array{bot: Bot, data: array<string, mixed>}|null
     */
    public function resolve(string $initData, ?string $botName = null): ?array
    {
        $parsed = $this->parse($initData);

        if (! $parsed || ! isset($parsed['hash'], $parsed['auth_date'])) {
            return null;
        }

        if (! $this->isFresh((int) $parsed['auth_date'])) {
            return null;
        }

        foreach ($this->candidateBots($botName) as $bot) {
            if ($bot->token && $this->signatureMatches($parsed, $bot->token)) {
                return ['bot' => $bot, 'data' => $parsed];
            }
        }

        return null;
    }

    /**
     * Telegram user object carried by initData, or null when it is absent.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>|null
     */
    public function extractUser(array $parsed): ?array
    {
        if (empty($parsed['user'])) {
            return null;
        }

        $user = json_decode((string) $parsed['user'], true);

        return is_array($user) && isset($user['id']) ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $initData): array
    {
        parse_str($initData, $parsed);

        return $parsed;
    }

    private function isFresh(int $authDate): bool
    {
        return $authDate > 0 && (time() - $authDate) <= self::MAX_AGE;
    }

    /**
     * Check the initData signature against a single bot token.
     *
     * @param  array<string, mixed>  $parsed
     */
    public function signatureMatches(array $parsed, string $token): bool
    {
        $hash = (string) $parsed['hash'];
        unset($parsed['hash'], $parsed['signature']);

        ksort($parsed);

        $pairs = [];
        foreach ($parsed as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $expected = hash_hmac('sha256', implode("\n", $pairs), $secret);

        return hash_equals($expected, $hash);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Bot>
     */
    private function candidateBots(?string $botName)
    {
        if ($botName) {
            return Bot::where('name', $botName)->get();
        }

        return Cache::remember('web_app_candidate_bots', 60, static function () {
            return Bot::where('active', true)->get();
        });
    }
}
