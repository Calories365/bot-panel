<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $counter
 * @property \Illuminate\Support\Carbon|null $premium_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\BotUser $botUser
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'counter',
        'premium_until',
    ];

    protected $casts = [
        'premium_until' => 'datetime',
    ];

    public function botUser()
    {
        return $this->belongsTo(BotUser::class, 'user_id');
    }

    public function isPremium(): bool
    {
        return $this->premium_until && $this->premium_until->copy()->addDay()->isFuture();
    }

    public function canTranscribeAudio(): bool
    {
        return $this->isPremium() || $this->counter < 11;
    }

    public function incrementTranscribeCounter(): void
    {
        $this->counter++;
        $this->save();
    }
}
