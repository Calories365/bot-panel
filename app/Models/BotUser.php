<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotUser extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'name',
        'username',
        'telegram_id',
        'premium',
        'is_banned',
        'phone'
    ];

    public function bots()
    {
        return $this->belongsToMany(Bot::class, 'bot_bot_user', 'bot_user_id', 'bot_id');
    }
}
