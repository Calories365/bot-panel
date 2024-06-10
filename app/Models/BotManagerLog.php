<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
