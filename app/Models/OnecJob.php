<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed question or follow-up waiting for the worker on the 1C server, in
 * its work, or done with its answer.
 *
 * @property int $id
 * @property int $question_id
 * @property int $batch_id
 * @property string $kind question|followup
 * @property string $status queued|processing|done|failed
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $heartbeat_at
 * @property \Illuminate\Support\Carbon|null $note_shown_at
 * @property \Illuminate\Support\Carbon|null $queued_alert_sent_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $session_id
 * @property string|null $for_user
 * @property string|null $for_boss
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read OnecQuestion $question
 * @property-read OnecBatch $batch
 */
class OnecJob extends Model
{
    public const KIND_QUESTION = 'question';

    public const KIND_FOLLOWUP = 'followup';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'question_id',
        'batch_id',
        'kind',
        'status',
        'attempts',
        'queued_at',
        'claimed_at',
        'heartbeat_at',
        'note_shown_at',
        'queued_alert_sent_at',
        'finished_at',
        'session_id',
        'for_user',
        'for_boss',
        'error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'claimed_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'note_shown_at' => 'datetime',
        'queued_alert_sent_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<OnecQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(OnecQuestion::class, 'question_id');
    }

    /**
     * @return BelongsTo<OnecBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OnecBatch::class, 'batch_id');
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
