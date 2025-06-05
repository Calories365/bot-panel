<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property int $telegram_id
 * @property bool $is_banned
 * @property string|null $phone
 * @property bool $premium
 * @property bool $premium_calories
 * @property string|null $source
 * @property string|null $email
 * @property string|null $username_calories
 * @property int|null $calories_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    //    protected $casts = [
    //        'is_banned' => 'boolean',
    //        'premium' => 'boolean',
    //        'premium_calories' => 'boolean',
    //    ];
}
