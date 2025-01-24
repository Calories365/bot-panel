<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaloriesUser extends Model
{
    protected $table = 'calories_users';

    protected $fillable = [
        'id',
        'name',
        'username',
        'telegram_id',
        'is_banned',
        'phone',
        'premium',
        'premium_calories',
        'created_at',
        'source',
        'email',
        'username_calories',
        'calories_id',
    ];
}
