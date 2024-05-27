<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'web_hook'
    ];

    public function users()
    {
        return $this->belongsToMany(BotUser::class, 'bot_bot_user', 'bot_id', 'bot_user_id');
    }
}
