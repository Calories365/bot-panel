<?php

namespace App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SaveCallbackQueryHandler implements CallbackQueryHandlerInterface
{

    public function handle($bot, $telegram, $callbackQuery)
    {
        $userId = $callbackQuery->getFrom()->getId();
        $data = Cache::get("user_products_{$userId}");
        Log::info(print_r($data, true));
        $telegram->answerCallbackQuery([
        'callback_query_id' => $callbackQuery->getId(),
    ]);
    }
}
