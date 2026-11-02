<?php

namespace App\Console\Commands;

use App\Jobs\SendOnecReplies;
use App\Models\OnecJob;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\OnecHelperHandlers\OnecJobBoard;
use App\Services\TelegramServices\OnecHelperHandlers\OnecTexts;
use Illuminate\Console\Command;

/**
 * The button "Повторить" for a failure the bot sent before it had one: the
 * failed job goes back to the queue, and the owner is told in reply to the
 * service message of its question or follow-up, or for a resume to the
 * message of its button.
 */
class RetryOnecJob extends Command
{
    protected $signature = 'onec-helper:retry {job : the id of the failed job in onec_jobs}';

    protected $description = 'Put a failed 1C helper job in the queue again, as the button "Повторить" does';

    public function handle(OnecJobBoard $board, BotApiFactory $apis): int
    {
        $job = OnecJob::with('question.bot')->find((int) $this->argument('job'));

        if ($job === null) {
            $this->error('No such job.');

            return self::FAILURE;
        }

        if (! $board->retry($apis->make($job->question->bot), $job)) {
            $this->warn('Nothing changed: the job is '.$job->status.', or not the latest of its batch.');

            return self::FAILURE;
        }

        SendOnecReplies::about($job, [OnecTexts::get($job->opensOnServer() ? 'retried_on_server' : 'retried')]);

        $this->info('The job is in the queue again.');

        return self::SUCCESS;
    }
}
