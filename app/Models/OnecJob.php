<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed question or follow-up waiting for the worker on the 1C server, in
 * its work, or done with its answer. A job may also open a session of Claude
 * on the server for the owner to work in: "window" with the messages of a
 * question gathered just now, "resume" with a copy of the session that
 * analysed a question. A resume has no batch: it answers the message its
 * button was under.
 *
 * @property int $id
 * @property int $question_id
 * @property int|null $batch_id
 * @property int|null $reply_to_message_id the message a job without a batch answers
 * @property string $kind question|followup|window|resume
 * @property string $status queued|processing|done|failed
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $heartbeat_at
 * @property \Illuminate\Support\Carbon|null $note_shown_at
 * @property \Illuminate\Support\Carbon|null $queued_alert_sent_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property string|null $session_id
 * @property string|null $attach_id the short id of the session for `claude attach`
 * @property string|null $folder the folder of the question on the server, relative to project_dir
 * @property string|null $project_dir the folder of the agent on the server
 * @property bool|null $window_opened whether the server opened a window of the session
 * @property string|null $for_user
 * @property string|null $for_boss
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read OnecQuestion $question
 * @property-read OnecBatch|null $batch
 */
class OnecJob extends Model
{
    public const KIND_QUESTION = 'question';

    public const KIND_FOLLOWUP = 'followup';

    public const KIND_WINDOW = 'window';

    public const KIND_RESUME = 'resume';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'question_id',
        'batch_id',
        'reply_to_message_id',
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
        'attach_id',
        'folder',
        'project_dir',
        'window_opened',
        'for_user',
        'for_boss',
        'error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'reply_to_message_id' => 'integer',
        'window_opened' => 'boolean',
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

    /**
     * The worker opens a session on the server for the owner and does not
     * analyse: its result is where the session is.
     */
    public function opensOnServer(): bool
    {
        return in_array($this->kind, [self::KIND_WINDOW, self::KIND_RESUME], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
