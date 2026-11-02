<?php

namespace App\Jobs;

use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\OnecHelperHandlers\OnecReplies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends to the owner of the 1C helper bot what came about a question: the
 * answer of the worker, where the session it opened on the server is, a
 * failure, a warning. In the queue, so that the worker is answered at once
 * and Telegram being down does not lose the texts.
 */
class SendOnecReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 600];

    /**
     * Inline buttons under the first text. A property with its own default:
     * a job queued before it existed unserializes with an empty keyboard.
     *
     * @var list<list<array<string, string>>>
     */
    public array $keyboard = [];

    /**
     * The job the texts are about: it tells which message they answer. Null
     * in a job queued before it existed, which answers the service message of
     * the batch.
     */
    public ?int $jobId = null;

    public ?string $parseMode = null;

    /**
     * @param  list<string|null>  $texts
     * @param  list<list<array<string, string>>>  $keyboard
     */
    public function __construct(public ?int $batchId, public array $texts, array $keyboard = [], ?int $jobId = null, ?string $parseMode = null)
    {
        $this->keyboard = $keyboard;
        $this->jobId = $jobId;
        $this->parseMode = $parseMode;
        $this->onQueue('telegram');
    }

    /**
     * @param  list<string|null>  $texts
     * @param  list<list<array<string, string>>>  $keyboard
     */
    public static function about(OnecJob $job, array $texts, array $keyboard = [], ?string $parseMode = null): PendingDispatch
    {
        return static::dispatch($job->batch_id, $texts, $keyboard, $job->id, $parseMode);
    }

    public function handle(OnecReplies $replies, BotApiFactory $apis): void
    {
        if ($this->jobId !== null) {
            $job = OnecJob::with('question.bot', 'batch')->find($this->jobId);

            if ($job !== null) {
                $replies->about($apis->make($job->question->bot), $job, $this->texts, $this->keyboard, $this->parseMode);
            }

            return;
        }

        $batch = OnecBatch::with('question.bot')->find($this->batchId);

        if ($batch === null) {
            return;
        }

        $replies->send($apis->make($batch->question->bot), $batch, $this->texts, $this->keyboard);
    }
}
