<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;

class BotBotUser extends Pivot
{
    use HasFactory;

    protected $table = 'bot_bot_users';
    protected $fillable = ['bot_id', 'bot_user_id'];

    public $timestamps = true;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            Log::info('Created BotBotUser model with ID: ' . $model->id);
        });
    }
}
