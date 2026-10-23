<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Telegram user who sent the password of a bot that asks for one.
 *
 * @property int $id
 * @property int $bot_id
 * @property int $telegram_id
 */
class BotAccess extends Model
{
    protected $fillable = [
        'bot_id',
        'telegram_id',
    ];

    public static function granted(int $botId, int $telegramId): bool
    {
        return static::where('bot_id', $botId)->where('telegram_id', $telegramId)->exists();
    }
}
