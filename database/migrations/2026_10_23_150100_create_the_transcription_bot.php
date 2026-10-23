<?php

use App\Models\Bot;
use App\Models\BotType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The transcription bot @transcrbbot. Its token comes from the environment
     * of the server, so databases without TRANSCRIPTION_BOT_TOKEN get no bot.
     * The webhook is set through Telegram only in production: local databases
     * would point the live bot at themselves.
     */
    public function up(): void
    {
        $token = config('services.transcription_bot.token');
        $type = BotType::query()->where('name', 'Transcription')->first();

        if (! $token || ! $type || Bot::query()->where('token', $token)->exists()) {
            return;
        }

        $secret = Str::random(48);

        $bot = Bot::query()->create([
            'name' => 'transcrbbot',
            'token' => $token,
            'secret_token' => hash('sha256', $secret),
            'type_id' => $type->id,
            'message' => 'Бот переводит голосовые сообщения в текст.',
            'active' => true,
            'web_hook' => Bot::query()->where('name', 'Calories365_bot')->value('web_hook') ?: config('app.url'),
        ]);

        Cache::forget('bot:'.$bot->name);

        if (app()->environment('production')) {
            $bot->updateWeebHook($secret);
        }
    }

    public function down(): void
    {
        //
    }
};
