<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The messages of a question, or of a follow-up to it, gathered under one
 * service message of the bot until the owner presses "Разобрать сейчас" or
 * stops writing.
 *
 * @property int $id
 * @property int $question_id
 * @property string $kind question|followup
 * @property string $status collecting|closed|cancelled
 * @property int|null $service_message_id
 * @property \Illuminate\Support\Carbon|null $service_edited_at
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read OnecQuestion $question
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OnecMessage> $messages
 */
class OnecBatch extends Model
{
    public const KIND_QUESTION = 'question';

    public const KIND_FOLLOWUP = 'followup';

    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'question_id',
        'kind',
        'status',
        'service_message_id',
        'service_edited_at',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'service_edited_at' => 'datetime',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<OnecQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OnecQuestion::class, 'question_id');
    }

    /**
     * In the order of Telegram, which is not the order the queue handled them in.
     *
     * @return HasMany<OnecMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(OnecMessage::class, 'batch_id')->orderBy('telegram_message_id');
    }

    public function isFollowup(): bool
    {
        return $this->kind === self::KIND_FOLLOWUP;
    }
}
