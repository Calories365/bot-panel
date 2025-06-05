<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $telegram_id
 * @property bool $is_last
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bot> $bots
 */
class Manager extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'telegram_id',
        'is_last',
    ];

    protected $casts = [
        'is_last' => 'boolean',
    ];

    public function bots()
    {
        return $this->belongsToMany(Bot::class);
    }
}
