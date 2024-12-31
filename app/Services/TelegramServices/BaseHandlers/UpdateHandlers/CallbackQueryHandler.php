<?php

namespace App\Services\TelegramServices\BaseHandlers\UpdateHandlers;

use Illuminate\Support\Facades\Log;

class CallbackQueryHandler implements UpdateHandlerInterface
{

    public function handle($bot, $telegram, $update, $botUser)
    {
        Log::info('CallbackQueryHandler');
        return true;
    }
}
