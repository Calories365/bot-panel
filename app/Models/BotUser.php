<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property int $telegram_id
 * @property bool $premium
 * @property bool $is_banned
 * @property string|null $phone
 * @property int|null $calories_id
 * @property string|null $locale
 * @property \Illuminate\Support\Carbon|null $last_active_at
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bot> $bots
 * @property-read \App\Models\Subscription|null $subscription
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bot> $banned_bots
 */
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
        'last_active_at',
    ];

    protected $casts = [
        'premium' => 'boolean',
        'is_banned' => 'boolean',
        'big_font' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(static function (self $botUser) {
            Cache::forget('tg_bot_user_'.$botUser->telegram_id);
        });

        static::deleted(static function (self $botUser) {
            Cache::forget('tg_bot_user_'.$botUser->telegram_id);
        });
    }

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

    public static function addOrUpdateUser($chatId, $firstName, $lastName, $username, $botId, $premium, $source = null, $result = null, $locale = null)
    {
        $fullName = $firstName.($lastName ? " {$lastName}" : '');

        $botUser = self::firstOrNew(['telegram_id' => $chatId]);
        $wasExists = $botUser->exists;

        $botUser->name = $fullName;
        $botUser->username = $username;
        $botUser->premium = (bool) $premium;

        if (! $wasExists) {
            $botUser->is_banned = false;
        }

        if ($locale) {
            if ($locale == 'uk') {
                $locale = 'ua';
            }

            $botUser->locale = $locale;
        }

        $botUser->save();

        if (! $wasExists && $source) {
            $botUser->source = $source;
            $botUser->save();
        }

        $botUser->loadMissing('bots');
        if ($botUser->bots->contains($botId)) {
            $botUser->bots()->detach($botId);
        }
        $botUser->bots()->syncWithoutDetaching([$botId]);

        if ($source == 'bot_link') {
            CaloriesUser::updateOrCreate(
                ['telegram_id' => $chatId],

                [
                    'name' => $botUser->name,
                    'username' => $botUser->username,
                    'telegram_id' => $botUser->telegram_id,
                    'is_banned' => $botUser->is_banned,
                    'phone' => $botUser->phone,
                    'premium' => $botUser->premium,
                    'premium_calories' => false,
                    'source' => $botUser->source ?: 'bot_only',
                    'email' => null,
                    'username_calories' => null,
                ]
            );
        } elseif ($source == 'calories') {

            CaloriesUser::where('calories_id', $result['user_id'])->delete();

            $caloriesUser = CaloriesUser::firstOrNew(['telegram_id' => $chatId]);

            $caloriesUser->name = $botUser->name;
            $caloriesUser->username = $botUser->username;
            $caloriesUser->telegram_id = $botUser->telegram_id;
            $caloriesUser->is_banned = $botUser->is_banned;
            $caloriesUser->phone = $botUser->phone;
            $caloriesUser->premium = $botUser->premium;
            $caloriesUser->premium_calories = $result['premium'] ?? 0;
            $caloriesUser->email = $result['email'];
            $caloriesUser->username_calories = $result['name'];
            $caloriesUser->calories_id = $result['user_id'];

            if (! $caloriesUser->exists) {
                $caloriesUser->source = $source;
            }

            $caloriesUser->save();
        }

        return $botUser;
    }
}
