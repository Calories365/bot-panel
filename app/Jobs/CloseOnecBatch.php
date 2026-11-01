<?php

namespace App\Jobs;

use App\Models\OnecBatch;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\OnecHelperHandlers\QuestionCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The timer of a question or follow-up of the 1C helper bot: dispatched with
 * every message for the batch time later, it closes the batch when no message
 * came since. The one of an earlier message finds a newer one and does nothing.
 */
class CloseOnecBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * The title and summary come from a model with a 45-second limit.
     */
    public int $timeout = 120;

    public function __construct(public int $batchId) {}

    public function handle(QuestionCollector $collector, BotApiFactory $apis): void
    {
        $batch = OnecBatch::with('question.bot')->find($this->batchId);

        if ($batch === null || $batch->status !== OnecBatch::STATUS_COLLECTING) {
            return;
        }

        $collector->close($apis->make($batch->question->bot), $batch, true);
    }
}
