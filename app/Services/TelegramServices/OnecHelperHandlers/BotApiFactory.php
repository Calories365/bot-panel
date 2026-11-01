<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\Bot;
use Telegram\Bot\Api;

/**
 * Telegram for the 1C helper bot outside a webhook: in the queued jobs and in
 * the worker's API, where no update brings an Api along. Tests put a mock in
 * its place.
 */
class BotApiFactory
{
    public function make(Bot $bot): Api
    {
        return new Api($bot->token);
    }
}
