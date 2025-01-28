<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BotUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'telegram_id',
        'premium',
        'is_banned',
        'phone',
        'calories_id',
        'locale',
        'last_active_at'
    ];

    public function bots()
    {
        return $this->belongsToMany(Bot::class, 'bot_user_bots')->withTimestamps();
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'user_id');
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

    public static function addOrUpdateUser($chatId, $firstName, $lastName, $username, $botId, $premium, $source = null, $result = null)
    {
        $fullName = $firstName . ($lastName ? " {$lastName}" : '');

        $botUser = self::firstOrNew(['telegram_id' => $chatId]);
        $wasExists = $botUser->exists;

        $botUser->name     = $fullName;
        $botUser->username = $username;
        $botUser->premium  = $premium ? 1 : 0;

        if (!$wasExists) {
            $botUser->is_banned = 0;
        }

        $botUser->save();

        if (!$wasExists && $source) {
            $botUser->source = $source;
            $botUser->save();
        }

        $botUser->loadMissing('bots');
        if ($botUser->bots->contains($botId)) {
            $botUser->bots()->detach($botId);
        }
        $botUser->bots()->syncWithoutDetaching([$botId]);

        if ($source == 'bot_link'){
            CaloriesUser::updateOrCreate(
                ['telegram_id' => $chatId],

                [
                    'name'              => $botUser->name,
                    'username'          => $botUser->username,
                    'telegram_id'       => $botUser->telegram_id,
                    'is_banned'         => $botUser->is_banned,
                    'phone'             => $botUser->phone,
                    'premium'           => $botUser->premium,
                    'premium_calories'  => false,
                    'source'            => $botUser->source ?: 'bot_only',
                    'email'             => null,
                    'username_calories' => null,
                ]
            );
        } elseif($source == 'calories'){

            CaloriesUser::where('calories_id', $result['user_id'])->delete();

            $caloriesUser = CaloriesUser::firstOrNew(['telegram_id' => $chatId]);

            $caloriesUser->name              = $botUser->name;
            $caloriesUser->username          = $botUser->username;
            $caloriesUser->telegram_id       = $botUser->telegram_id;
            $caloriesUser->is_banned         = $botUser->is_banned;
            $caloriesUser->phone             = $botUser->phone;
            $caloriesUser->premium           = $botUser->premium;
            $caloriesUser->premium_calories  = $result['premium'] ?? 0;
            $caloriesUser->email             = $result['email'];
            $caloriesUser->username_calories = $result['name'];
            $caloriesUser->calories_id = $result['user_id'];

            if (!$caloriesUser->exists) {
                $caloriesUser->source = $source;
            }

            $caloriesUser->save();
        }

        return $botUser;
    }
}
