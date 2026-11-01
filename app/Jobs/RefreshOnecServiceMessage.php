<?php

namespace App\Jobs;

use App\Models\OnecBatch;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\OnecHelperHandlers\QuestionCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Shows the counters of a batch being gathered when they changed too soon
 * after the last edit. One waits per batch: messages that come meanwhile are
 * counted when it runs; one that comes while it runs dispatches the next.
 */
class RefreshOnecServiceMessage implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(public int $batchId) {}

    public function uniqueId(): string
    {
        return (string) $this->batchId;
    }

    public function handle(QuestionCollector $collector, BotApiFactory $apis): void
    {
        $batch = OnecBatch::with('question.bot')->find($this->batchId);

        if ($batch === null) {
            return;
        }

        $collector->refresh($apis->make($batch->question->bot), $batch, true);
    }
}
