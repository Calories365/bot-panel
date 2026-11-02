<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question about the 1C base gathered by the 1C helper bot from the
 * messages of its owner, with the follow-ups to it.
 *
 * @property int $id
 * @property int $bot_id
 * @property int $chat_id
 * @property string|null $title
 * @property string|null $summary
 * @property array<string, list<string>>|null $entities
 * @property string|null $session_id the worker's agent session, continued by follow-ups
 * @property \Illuminate\Support\Carbon|null $files_deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Bot $bot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OnecBatch> $batches
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OnecJob> $jobs
 */
class OnecQuestion extends Model
{
    protected $fillable = [
        'bot_id',
        'chat_id',
        'title',
        'summary',
        'entities',
        'session_id',
        'files_deleted_at',
    ];

    protected $casts = [
        'entities' => 'array',
        'files_deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Bot, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /**
     * @return HasMany<OnecBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(OnecBatch::class, 'question_id');
    }

    /**
     * @return HasMany<OnecJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(OnecJob::class, 'question_id');
    }

    /**
     * The batch the question itself was gathered in.
     */
    public function questionBatch(): ?OnecBatch
    {
        return $this->batches()->where('kind', OnecBatch::KIND_QUESTION)->first();
    }

    /**
     * The owner opened the question on the 1C server instead of having it
     * analysed: it has a job "window" and none "question", and the owner goes
     * on in the session on the server.
     */
    public function isOpenedOnServer(): bool
    {
        return $this->jobs()->where('kind', OnecJob::KIND_WINDOW)->exists()
            && ! $this->jobs()->where('kind', OnecJob::KIND_QUESTION)->exists();
    }

    /**
     * The short id for `claude attach` of the session the question was opened
     * in on the server, once the worker gave it.
     */
    public function attachId(): ?string
    {
        return $this->jobs()
            ->where('kind', OnecJob::KIND_WINDOW)
            ->whereNotNull('attach_id')
            ->latest('id')
            ->value('attach_id');
    }

    /**
     * A follow-up continues the worker's session of the question: it can wait
     * for a question still in the queue or in work, not for one that failed,
     * was cancelled or was opened on the server. A closed question without a
     * job is one whose job is not created yet.
     */
    public function takesFollowups(): bool
    {
        if ($this->session_id !== null) {
            return true;
        }

        if ($this->isOpenedOnServer()) {
            return false;
        }

        $job = $this->jobs()->where('kind', OnecJob::KIND_QUESTION)->latest('id')->first();

        if ($job !== null) {
            return in_array($job->status, [OnecJob::STATUS_QUEUED, OnecJob::STATUS_PROCESSING], true);
        }

        return $this->batches()
            ->where('kind', OnecBatch::KIND_QUESTION)
            ->where('status', OnecBatch::STATUS_CLOSED)
            ->exists();
    }
}
