<?php

namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\CaloriesUser;
use App\Services\TelegramServices\WebAppInitDataService;
use App\Utilities\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the Calories365 Mini App: the bot tokens needed to verify initData
 * live here, so Calories365 asks the panel instead of holding copies of them.
 */
class TelegramWebAppController extends Controller
{
    protected WebAppInitDataService $webAppInitDataService;

    public function __construct(WebAppInitDataService $webAppInitDataService)
    {
        $this->webAppInitDataService = $webAppInitDataService;
    }

    /**
     * Verify initData signature and return the Telegram user behind it.
     */
    public function validateInitData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'init_data' => 'required|string',
            'bot' => 'nullable|string|max:255',
        ]);

        $resolved = $this->webAppInitDataService->resolve($data['init_data'], $data['bot'] ?? null);

        if (! $resolved) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired init data',
            ], 401);
        }

        $telegramUser = $this->webAppInitDataService->extractUser($resolved['data']);

        if (! $telegramUser) {
            return response()->json([
                'valid' => false,
                'message' => 'Init data carries no user',
            ], 422);
        }

        $bot = $resolved['bot'];
        $locale = $telegramUser['language_code'] ?? null;

        $botUser = Utilities::saveAndNotify(
            $telegramUser['id'],
            $telegramUser['first_name'] ?? null,
            $telegramUser['last_name'] ?? null,
            $telegramUser['username'] ?? null,
            $bot,
            $telegramUser['is_premium'] ?? false,
            'mini_app',
            null,
            $locale
        );

        return response()->json([
            'valid' => true,
            'bot' => $bot->name,
            'calories_id' => $botUser->calories_id,
            'locale' => $botUser->locale,
            'user' => [
                'telegram_id' => (int) $telegramUser['id'],
                'first_name' => $telegramUser['first_name'] ?? null,
                'last_name' => $telegramUser['last_name'] ?? null,
                'username' => $telegramUser['username'] ?? null,
                'language_code' => $locale,
                'is_premium' => (bool) ($telegramUser['is_premium'] ?? false),
                'photo_url' => $telegramUser['photo_url'] ?? null,
            ],
        ]);
    }

    /**
     * Bind a Telegram account to a Calories365 account — the Mini App equivalent
     * of the /start CODE flow.
     */
    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_id' => 'required|integer',
            'calories_id' => 'required|integer',
            'locale' => 'nullable|string|max:5',
            'email' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $botUser = BotUser::where('telegram_id', $data['telegram_id'])->first();

        if (! $botUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bot user not found',
            ], 404);
        }

        $botUser->calories_id = $data['calories_id'];

        if (! empty($data['locale'])) {
            $botUser->locale = $data['locale'] === 'uk' ? 'ua' : $data['locale'];
        }

        $botUser->save();

        $caloriesUser = CaloriesUser::where('telegram_id', $data['telegram_id'])->first()
            ?: CaloriesUser::where('calories_id', $data['calories_id'])->first()
            ?: new CaloriesUser;

        $caloriesUser->fill([
            'calories_id' => $data['calories_id'],
            'telegram_id' => $botUser->telegram_id,
            'name' => $botUser->name,
            'username' => $botUser->username,
            'is_banned' => $botUser->is_banned,
            'phone' => $botUser->phone,
            'premium' => $botUser->premium,
            'email' => $data['email'] ?? $caloriesUser->email,
            'username_calories' => $data['name'] ?? $caloriesUser->username_calories,
            'source' => $caloriesUser->source ?: 'mini_app',
        ]);

        $caloriesUser->save();

        return response()->json(['status' => 'ok']);
    }
}
