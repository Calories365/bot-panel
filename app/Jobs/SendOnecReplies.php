<?php

namespace App\Jobs;

use App\Models\OnecBatch;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\OnecHelperHandlers\OnecReplies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends to the owner of the 1C helper bot what came about a question: the
 * answer of the worker, a failure, a warning. In the queue, so that the worker
 * is answered at once and Telegram being down does not lose the texts.
 */
class SendOnecReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 120, 600];

    /**
     * Inline buttons under the last text. A property with its own default:
     * a job queued before it existed unserializes with an empty keyboard.
     *
     * @var list<list<array<string, string>>>
     */
    public array $keyboard = [];

    /**
     * @param  list<string|null>  $texts
     * @param  list<list<array<string, string>>>  $keyboard
     */
    public function __construct(public int $batchId, public array $texts, array $keyboard = [])
    {
        $this->keyboard = $keyboard;
        $this->onQueue('telegram');
    }

    public function handle(OnecReplies $replies, BotApiFactory $apis): void
    {
        $batch = OnecBatch::with('question.bot')->find($this->batchId);

        if ($batch === null) {
            return;
        }

        $replies->send($apis->make($batch->question->bot), $batch, $this->texts, $this->keyboard);
    }
}
