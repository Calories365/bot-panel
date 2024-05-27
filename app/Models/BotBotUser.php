<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotBotUser extends Model
{
    use HasFactory;
    protected $fillable = ['bot_id', 'bot_user_id'];

}
