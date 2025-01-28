<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

    /**
     * Связь "подписка принадлежит конкретному bot_user"
     */
    public function botUser()
    {
        return $this->belongsTo(BotUser::class, 'user_id');
    }

    /**
     * Проверяем, действует ли у пользователя премиум.
     * Если premium_until больше текущего момента, значит премиум ещё активен.
     */
    public function isPremium(): bool
    {
        return $this->premium_until && $this->premium_until->isFuture();
    }

    /**
     * Может ли пользователь расшифровать аудио:
     * - если у пользователя премиум, то ограничений нет,
     * - если не премиум, то пока счётчик < 10.
     */
    public function canTranscribeAudio(): bool
    {
        return $this->isPremium() || $this->counter < 2;
    }

    /**
     * Увеличиваем счётчик бесплатных расшифровок на 1 (если пользователь не премиум).
     */
    public function incrementTranscribeCounter(): void
    {
        $this->counter++;
        $this->save();
    }
}

