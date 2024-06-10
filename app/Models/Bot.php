<?php

namespace App\Models;

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
        'web_hook'
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
            return false;
        }
    }

    public function notifyAdmins($message, $delaySeconds = 2)
    {
        $admins = BotAdmin::all();
        foreach ($admins as $admin) {
            dispatch(function () use ($admin, $message) {
                try {
                    $telegram = new Api($this->token);
                    $telegram->sendMessage([
                        'chat_id' => $admin->telegram_id,
                        'text' => $message,
                    ]);
                    Log::info('Sent message: ' . $message);
                } catch (\Exception $e) {
                    Log::error('Error sending message to ' . $admin->telegram_id . ': ' . $e->getMessage());
                }
            })->delay(now()->addSeconds($delaySeconds));
        }
    }


    public function notifyManagers(Bot $bot, $message, $delaySeconds = 2): void
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

            dispatch(function () use ($nextManager, $message, $delaySeconds) {
                try {
                    $telegram = new Api($this->token);
                    sleep($delaySeconds);
                    $telegram->sendMessage([
                        'chat_id' => $nextManager->telegram_id,
                        'text' => $message,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error sending Req-message(' . $message . ') to ' . $nextManager->telegram_id . ': ' . $e->getMessage());
                }
            });
        } else {
            Log::info('No managers available for bot ID ' . $bot->id);
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
