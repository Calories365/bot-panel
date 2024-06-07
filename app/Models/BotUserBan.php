<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BotUserBan extends Model
{
    use HasFactory;

    public static function getBannedUsersStatistics($botId, $startDate, $endDate)
    {
        return static::where('bot_id', $botId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();
    }
}
