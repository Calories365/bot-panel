<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class BannedBotUser extends Pivot
{
    public $timestamps = true;

    protected $table = 'banned_bot_user_relations';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
            $model->updated_at = $model->freshTimestamp();
        });
    }
    public function notifyAdmins($message, $delaySeconds = 2): void
    {
        $admins = BotAdmin::all();
        foreach ($admins as $admin) {
            dispatch(function () use ($admin, $message) {
                Log::info('Sent message: ' . $message);
                $telegram = new Api($this->token);
                $telegram->sendMessage([
                    'chat_id' => $admin->telegram_id,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ]);
            })->delay(now()->addSeconds($delaySeconds));
        }
    }

}
