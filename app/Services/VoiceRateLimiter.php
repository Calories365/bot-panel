<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Spam guard for voice input, per Calories365 account, shared by the bot and the
 * site (the diary asks /api/subscription-check before an upload). Voice input has
 * no paid limit; every recording costs a transcription and an analysis, so a
 * flood of them is cut off.
 */
class VoiceRateLimiter
{
    private const WINDOW_SECONDS = 3600;

    /**
     * Count one recording; false when the account already used up the hour.
     */
    public function attempt(int $caloriesId): bool
    {
        return RateLimiter::attempt(
            $this->key($caloriesId),
            (int) config('services.voice.max_per_hour'),
            static fn () => true,
            self::WINDOW_SECONDS
        ) !== false;
    }

    /**
     * Whole minutes until the account may record again, at least one.
     */
    public function minutesUntilAvailable(int $caloriesId): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn($this->key($caloriesId)) / 60));
    }

    private function key(int $caloriesId): string
    {
        return 'voice:'.$caloriesId;
    }
}
