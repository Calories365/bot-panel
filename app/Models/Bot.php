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

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BotUser::class, 'bot_bot_users', 'bot_id', 'bot_user_id');
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BotType::class, 'type_id');
    }

    public function banned_users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BotUser::class, 'banned_bot_user_relations')
            ->using(BannedBotUser::class);
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
                Log::info('Sent message: ' . $message);
                $telegram = new Api($this->token); // Используйте токен этого бота
                $telegram->sendMessage([
                    'chat_id' => $admin->telegram_id,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
            })->delay(now()->addSeconds($delaySeconds));
        }
    }

}
