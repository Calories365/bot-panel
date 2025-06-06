<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bot_id
 * @property int $manager_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Bot $bot
 * @property-read \App\Models\Manager $manager
 */
class BotManagerLog extends Model
{
    use HasFactory;

    protected $fillable = ['bot_id', 'manager_id'];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }

    public function manager()
    {
        return $this->belongsTo(Manager::class);
    }
}
