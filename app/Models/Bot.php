<?php

namespace App\Models;

use App\Jobs\SendAdminNotification;
use App\Jobs\SendManagerNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'token',
        'message',
        'active',
        'message_image',
        'type_id',
        'wordpress_endpoint',
        'web_hook',
        'video_ru',
        'video_ua',
        'video_eng',
    ];

    public function users()
    {
        return $this->belongsToMany(BotUser::class, 'bot_user_bots')->withTimestamps();
    }

    public function managers()
    {
        return $this->belongsToMany(Manager::class);
    }

    public function type()
    {
        return $this->belongsTo(BotType::class, 'type_id');
    }

    public function banned_users()
    {
        return $this->belongsToMany(BotUser::class, 'bot_user_bans')->withTimestamps();
    }

    public function updateWeebHook(): bool
    {
        try {
            $telegram = new Api($this->token);
            $webHook = $this->web_hook;
            $url = $webHook . '/api/webhook/bot/' . $this->name;
            $telegram->setWebhook(['url' => $url]);
            return true;
        } catch (\Exception $e) {
            Log::info('error during updating webhook');
            return false;
        }
    }

    public function notifyAdmins($message, $delaySeconds = 2)
    {
        $admins = BotAdmin::all();
        $delaySeconds = (int)$delaySeconds;
        foreach ($admins as $admin) {
            SendAdminNotification::dispatch($this, $admin, $message)->delay(now()->addSeconds($delaySeconds));
        }
    }


    public function notifyManagers(Bot $bot, $message): void
    {
        $lastManagerLog = BotManagerLog::where('bot_id', $bot->id)->first();
        $lastManagerId = $lastManagerLog ? $lastManagerLog->manager_id : null;

        $query = $bot->managers()->orderBy('id');
        if ($lastManagerId !== null) {
            $query = $query->where('id', '>', $lastManagerId);
        }

        $nextManager = $query->first();

        if (!$nextManager) {
            $nextManager = $bot->managers()->orderBy('id')->first();
        }

        if ($nextManager) {
            BotManagerLog::updateOrCreate(
                ['bot_id' => $bot->id],
                ['manager_id' => $nextManager->id]
            );
            Log::info('trying to send msg: ' . $message . ' to manager ' . $nextManager->name);
            SendManagerNotification::dispatch($bot, $nextManager, $message);
        } else {
            Log::info('No managers available for bot ID ' . $bot->id);
        }
    }

    public function notifyAllManagers($bot, $message): void
    {
        $currentManager = Manager::where('is_last', true)->firstOr(function () {
            return Manager::first();
        });


        if ($currentManager) {
            $currentManager->is_last = false;
            $currentManager->save();

            $nextManager = Manager::where('id', '>', $currentManager->id)->first();

            if (!$nextManager) {

                $nextManager = Manager::first();
            }

            $nextManager->is_last = true;

            $nextManager->save();

            Log::info('Preparing to send message( ' . $message . ') to manager: ' . $nextManager->name);
            SendManagerNotification::dispatch($bot, $nextManager, $message);
        }
    }


    private function escapeMarkdown($text): array|string
    {
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!'],
            $text
        );
    }


}
