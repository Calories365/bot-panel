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
        return $this->belongsToMany(Bot::class, 'bot_bot_users', 'bot_user_id', 'bot_id');
    }

    public function banned_bots()
    {
        return $this->belongsToMany(Bot::class, 'banned_bot_user')
            ->using(BannedBotUser::class);
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
        $botUser = self::firstOrCreate(
            ['telegram_id' => $chatId],
            [
                'name' => $firstName . ($lastName ? " {$lastName}" : ''),
                'username' => $username,
                'premium' => $premium ? 1 : 0,
                'is_banned' => 0
            ]
        );

        $botUser->load('banned_bots');

        if ($botUser->banned_bots->contains('bot_id', $botId)) {
            $botUser->banned_bots()->detach($botId);
        }

        $botUser->bots()->syncWithoutDetaching([$botId]);

        return $botUser;
    }
}
