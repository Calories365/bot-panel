<?php

namespace App\Http\Middleware;

use App\Models\Bot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CheckTelegramSecretToken
{
    public function handle(Request $request, Closure $next): \Illuminate\Http\JsonResponse
    {
        $key = sprintf('tg-webhook:%s', $request->ip());
        if (RateLimiter::tooManyAttempts($key, 120)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }
        RateLimiter::hit($key, 60);

        $apiKey = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($apiKey === null) {
            return response()->json(['error' => 'Missing API key'], 403);
        }

        $botName = $request->route('bot');
        $bot = Bot::where('name', $botName)->first();

        if ($bot === null) {
            return response()->json(['error' => 'Bot not found'], 404);
        }

        $hashedApiKey = hash('sha256', $apiKey);

        if ($hashedApiKey !== $bot->secret_token) {
            return response()->json(['error' => 'Invalid API key'], 403);
        }

        return $next($request);
    }
}
