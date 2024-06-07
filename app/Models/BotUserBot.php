<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BotUserBot extends Model
{
    use HasFactory;

    /**
     * Получить связанного пользователя.
     */
    public function botUser()
    {
        return $this->belongsTo(BotUser::class);
    }

    /**
     * Получить связанного бота.
     */
    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public static function getNewUsersStatistics($botId, $startDate, $endDate)
    {
        return static::where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();
    }

    public static function getPremiumUsersStatistics($botId, $startDate, $endDate)
    {
        return static::where('bot_id', $botId)
            ->whereHas('botUser', function ($query) {
                $query->where('premium', 1);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();
    }
}
