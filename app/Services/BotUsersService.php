<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotUser;
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
        $filePath = 'public/exports/' . $fileName;
        Storage::disk('public')->put($filePath, $content);
        return Storage::disk('public')->url($filePath);
    }
}
