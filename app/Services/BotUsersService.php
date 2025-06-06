<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotUser;
use App\Models\BotUserBan;
use App\Models\BotUserBot;
use DateInterval;
use DatePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BotUsersService
{
    public function exportUsers($botId = null)
    {
        if ($botId) {
            $bot = Bot::findOrFail($botId);
            $users = $bot->users()
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->get(['username']);
        } else {
            $users = BotUser::whereNotNull('username')
                ->where('username', '!=', '')
                ->get(['username']);
        }

        return $users->pluck('username')->implode("\n");
    }

    public function saveToFile($content, $fileName = 'usernames.txt'): string
    {
        $filePath = 'public/exports/'.$fileName;
        Storage::put($filePath, $content);
        $url = Storage::url($filePath);
        Storage::disk('public')->put($filePath, $content);

        return Storage::disk('public')->url($filePath);
    }

    public function getBotUserData(Bot $bot): array
    {
        $endDate = now();
        $startDate = now()->subDays(7);

        $dateRange = new DatePeriod(
            $startDate,
            new DateInterval('P1D'),
            $endDate->addDay()
        );

        $dates = array_fill_keys(
            array_map(
                function ($date) {
                    return $date->format('Y-m-d');
                },
                iterator_to_array($dateRange)
            ),
            0
        );

        $newUsersStats = $dates;
        $bannedUsersStats = $dates;
        $premiumUsersStats = $dates;
        $activeUsersStats = $dates;

        $botId = $bot->id;

        $newUsersData = BotUserBot::getNewUsersStatistics($botId, $startDate, $endDate);

        foreach ($newUsersData as $data) {
            $newUsersStats[$data->date] = $data->count;
        }

        $bannedUsersData = BotUserBan::getBannedUsersStatistics($botId, $startDate, $endDate);
        foreach ($bannedUsersData as $data) {
            $bannedUsersStats[$data->date] = $data->count;
        }

        $premiumUsersData = BotUserBot::getPremiumUsersStatistics($botId, $startDate, $endDate);
        foreach ($premiumUsersData as $data) {
            $premiumUsersStats[$data->date] = $data->count;
        }

        foreach ($dates as $date => $zero) {
            if ($date === now()->format('Y-m-d')) {
                $activeCount = BotUser::query()
                    ->whereDate('last_active_at', $date)
                    ->count();
            } else {
                $activeCount = DB::table('daily_activity')
                    ->whereDate('date', $date)
                    ->value('count') ?? 0;
            }
            $activeUsersStats[$date] = $activeCount;
        }

        $totalNewUsers = array_sum($newUsersStats);
        $totalBannedUsers = array_sum($bannedUsersStats);
        $totalPremiumUsers = array_sum($premiumUsersStats);
        $totalActiveUsers = array_sum($activeUsersStats);
        $totalDefaultUsers = array_sum($newUsersStats) - array_sum($premiumUsersStats);

        return [
            'new_users' => $newUsersStats,
            'banned_users' => $bannedUsersStats,
            'premium_users' => $premiumUsersStats,
            'active_users' => $activeUsersStats,
            'total_active_users' => $totalActiveUsers,
            'total_new_users' => $totalNewUsers,
            'total_banned_users' => $totalBannedUsers,
            'total_premium_users' => $totalPremiumUsers,
            'total_default_users' => $totalDefaultUsers,
        ];
    }
}
