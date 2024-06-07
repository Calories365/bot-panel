<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'telegram_id',
        'premium',
        'is_banned',
        'phone'
    ];

    public function bots()
    {
        return $this->belongsToMany(Bot::class, 'bot_user_bot')->withTimestamps();
    }

    public function banned_bots()
    {
        return $this->belongsToMany(Bot::class, 'bot_user_bans')->withTimestamps();
    }

    public static function getPaginatedUsers($perPage, $botId = null)
    {
        $query = self::with('bots')->orderBy('created_at', 'desc');

        if ($botId) {
            $query->whereHas('bots', function ($query) use ($botId) {
                $query->where('bots.id', $botId);
            });
        }

        return $query->paginate($perPage);
    }

    public static function addOrUpdateUser($chatId, $firstName, $lastName, $username, $botId, $premium)
    {
        $fullName = $firstName . ($lastName ? " {$lastName}" : '');

        $botUser = self::firstOrCreate(
            ['telegram_id' => $chatId],
            [
                'name' => $fullName,
                'username' => $username,
                'premium' => $premium ? 1 : 0,
                'is_banned' => 0
            ]
        );

        $botUser->loadMissing('bots');

        if ($botUser->bots->contains($botId)) {
            $botUser->bots()->detach($botId);
        }

        $botUser->bots()->syncWithoutDetaching([$botId]);

        return $botUser;
    }
}
