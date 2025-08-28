<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('telegram', function ($job) {
            $bot = $job->botName ?? 'unknown-bot';
            $chatId =
                data_get($job->payload, 'message.chat.id') ??
                data_get($job->payload, 'callback_query.message.chat.id') ??
                data_get($job->payload, 'my_chat_member.chat.id') ??
                'unknown-chat';

            return Limit::perMinute(60)->by("bot:$bot|chat:$chatId");
        });

    }
}
