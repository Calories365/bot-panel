<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Jobs\SendOnecReplies;
use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Models\OnecMessage;
use App\Models\OnecQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Api;

/**
 * The jobs of the 1C helper bot as the worker on the 1C server sees them
 * through /api/onec-helper: it takes the oldest it can, tells it is alive, and
 * gives back the answer, the session it opened on the server, or the failure.
 * The scheduler returns the jobs of a worker that went silent and warns the
 * owner of a queue nobody takes.
 */
class OnecJobBoard
{
    public function __construct(
        protected QuestionCollector $collector,
        protected BotApiFactory $apis,
    ) {}

    /**
     * Give the oldest job the worker can take and mark it as in work.
     */
    public function claim(): ?OnecJob
    {
        return Cache::lock('onec-helper:claim', 30)->block(10, function () {
            $jobs = OnecJob::queued()->with('question')->orderBy('id')->get();

            foreach ($jobs as $job) {
                if (! $this->isAvailable($job)) {
                    continue;
                }

                $job->update([
                    'status' => OnecJob::STATUS_PROCESSING,
                    'claimed_at' => now(),
                    'heartbeat_at' => now(),
                    'note_shown_at' => null,
                    'attempts' => $job->attempts + 1,
                ]);

                return $job;
            }

            return null;
        });
    }

    /**
     * A job waits for the title the model gives its question on closing. A
     * follow-up continues the worker's session of the question: it waits for
     * the session and for any job of the question in work.
     */
    public function isAvailable(OnecJob $job): bool
    {
        if ($job->question->title === null) {
            return false;
        }

        if ($job->kind !== OnecJob::KIND_FOLLOWUP) {
            return true;
        }

        return $job->question->session_id !== null
            && ! OnecJob::where('question_id', $job->question_id)
                ->where('status', OnecJob::STATUS_PROCESSING)
                ->exists();
    }

    /**
     * The job as the worker reads it. The screenshots of a follow-up are
     * numbered on from those of the question and of the follow-ups before.
     * A resume has no batch: it gets the messages of the question.
     *
     * @return array<string, mixed>
     */
    public function payload(OnecJob $job): array
    {
        $question = $job->question;
        $batches = $question->batches()
            ->when($job->batch_id !== null, fn ($query) => $query->where('id', '<=', $job->batch_id))
            ->where('status', '!=', OnecBatch::STATUS_CANCELLED)
            ->with('messages')
            ->orderBy('id')
            ->get();

        $screen = 0;
        $messages = [];
        foreach ($batches as $batch) {
            [$messages[$batch->id], $screen] = $this->messages($batch->messages, $screen);
        }

        $questionBatch = $batches->firstWhere('kind', OnecBatch::KIND_QUESTION);

        return [
            'job' => [
                'id' => $job->id,
                'kind' => $job->kind,
                'attempt' => $job->attempts,
            ],
            'question' => [
                'id' => $question->id,
                'title' => $question->title,
                'summary' => (string) $question->summary,
                'entities' => (object) ($question->entities ?? []),
                'session_id' => $question->session_id,
                'created_at' => $question->created_at?->toIso8601String(),
            ],
            'messages' => $questionBatch ? $messages[$questionBatch->id] : [],
            'followup' => $job->kind === OnecJob::KIND_FOLLOWUP
                ? ['id' => $job->batch_id, 'messages' => $messages[$job->batch_id] ?? []]
                : null,
        ];
    }

    /**
     * The file of a message the worker may download: there is one, and it was
     * not deleted.
     */
    public function file(int $id): ?OnecMessage
    {
        return OnecMessage::whereKey($id)
            ->whereIn('type', [OnecMessage::TYPE_PHOTO, OnecMessage::TYPE_DOCUMENT])
            ->whereNotNull('file_path')
            ->whereNull('file_deleted_at')
            ->first();
    }

    /**
     * The worker is alive; its note goes to the service message, at most once
     * in config('onec_helper.note_interval_seconds'). A resume has no service
     * message, and its note is not shown. False when the job is not in work.
     */
    public function heartbeat(OnecJob $job, ?string $note): bool
    {
        if ($job->status !== OnecJob::STATUS_PROCESSING) {
            return false;
        }

        $note = trim((string) $note);
        $interval = (int) config('onec_helper.note_interval_seconds');
        $showNote = $note !== '' && ($job->note_shown_at === null || $job->note_shown_at->lte(now()->subSeconds($interval)));

        $job->update(['heartbeat_at' => now()] + ($showNote ? ['note_shown_at' => now()] : []));

        if ($showNote && $job->batch !== null) {
            $bot = $job->question->bot;
            $this->collector->showNote($this->apis->make($bot), $job, mb_substr($note, 0, 500));
        }

        return true;
    }

    /**
     * Keep the answer, give the session to the question and send both texts
     * to the owner, the first with the button "Продолжить на сервере". False
     * when the job already has its answer.
     */
    public function result(OnecJob $job, string $sessionId, string $forUser, ?string $forBoss): bool
    {
        $saved = OnecJob::whereKey($job->id)
            ->where('status', '!=', OnecJob::STATUS_DONE)
            ->update([
                'status' => OnecJob::STATUS_DONE,
                'session_id' => $sessionId,
                'for_user' => $forUser,
                'for_boss' => $forBoss,
                'error' => null,
                'finished_at' => now(),
            ]);

        if ($saved === 0) {
            return false;
        }

        $job->question->update(['session_id' => $sessionId]);

        SendOnecReplies::about($job, [
            OnecTexts::get('answer_heading')."\n".$forUser,
            $forBoss,
        ], [[
            ['text' => OnecTexts::get('continue_on_server'), 'callback_data' => 'onec_resume_'.$job->question_id],
        ]]);

        return true;
    }

    /**
     * Keep where the worker opened the session of a job "window" or "resume"
     * and tell the owner how to attach to it. The session of the question
     * stays the one of its analysis: the follow-ups go on in it. False when
     * the job already has its result.
     */
    public function opened(OnecJob $job, string $sessionId, string $attachId, ?string $folder, string $projectDir, bool $window): bool
    {
        $saved = OnecJob::whereKey($job->id)
            ->where('status', '!=', OnecJob::STATUS_DONE)
            ->update([
                'status' => OnecJob::STATUS_DONE,
                'session_id' => $sessionId,
                'attach_id' => $attachId,
                'folder' => $folder,
                'project_dir' => $projectDir,
                'window_opened' => $window,
                'error' => null,
                'finished_at' => now(),
            ]);

        if ($saved === 0) {
            return false;
        }

        SendOnecReplies::about($job, [
            OnecTexts::get($window ? 'window_opened' : 'window_not_opened', ['id' => e($attachId)]),
        ], [], 'HTML');

        return true;
    }

    /**
     * The owner asks to go on with the analysed question in a session on the
     * server: a job "resume" without a batch, answered in reply to the message
     * of the button. False, and nothing changed, when the question has no
     * session or a resume of it is in the queue or in work.
     */
    public function resume(OnecQuestion $question, int $replyTo): bool
    {
        return Cache::lock('onec-helper:resume:'.$question->id, 30)->block(10, function () use ($question, $replyTo) {
            $waiting = OnecJob::where('question_id', $question->id)
                ->where('kind', OnecJob::KIND_RESUME)
                ->whereIn('status', [OnecJob::STATUS_QUEUED, OnecJob::STATUS_PROCESSING])
                ->exists();

            if ($question->fresh()->session_id === null || $waiting) {
                return false;
            }

            OnecJob::create([
                'question_id' => $question->id,
                'batch_id' => null,
                'reply_to_message_id' => $replyTo,
                'kind' => OnecJob::KIND_RESUME,
                'status' => OnecJob::STATUS_QUEUED,
                'queued_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Mark the job failed and tell the owner, with the button "Повторить".
     * The follow-ups waiting for a question that never got a session fail
     * with it, each with a button of its own. False when the job is already
     * finished.
     */
    public function fail(OnecJob $job, string $error): bool
    {
        $failed = OnecJob::whereKey($job->id)
            ->whereIn('status', [OnecJob::STATUS_QUEUED, OnecJob::STATUS_PROCESSING])
            ->update([
                'status' => OnecJob::STATUS_FAILED,
                'error' => $error,
                'finished_at' => now(),
            ]);

        if ($failed === 0) {
            return false;
        }

        SendOnecReplies::about($job, [
            OnecTexts::get($job->opensOnServer() ? 'failed_on_server' : 'failed', ['error' => mb_substr($error, 0, 300)]),
        ], [[
            ['text' => OnecTexts::get('retry'), 'callback_data' => 'onec_retry_'.$job->id],
        ]]);

        $question = $job->question->fresh();

        if ($job->kind === OnecJob::KIND_QUESTION && $question->session_id === null) {
            OnecJob::queued()
                ->where('question_id', $question->id)
                ->where('kind', OnecJob::KIND_FOLLOWUP)
                ->get()
                ->each(fn (OnecJob $followup) => $this->fail($followup, OnecTexts::get('question_not_analyzed')));
        }

        return true;
    }

    /**
     * The owner asks to run a failed job again, once the worker on the 1C
     * server can: the job goes back to the queue as a new one, and its
     * service message shows it queued. Only the latest job of its batch is
     * taken again, for a resume the latest resume of its question; the
     * follow-ups that failed with their question stay failed. False, and
     * nothing changed, for any other job.
     */
    public function retry(Api $telegram, OnecJob $job): bool
    {
        // A null batch_id is "batch_id is null" here: the resumes of the question.
        $isLatest = ! OnecJob::where('question_id', $job->question_id)
            ->where('batch_id', $job->batch_id)
            ->where('id', '>', $job->id)
            ->exists();

        if (! $isLatest) {
            return false;
        }

        $retried = OnecJob::whereKey($job->id)
            ->where('status', OnecJob::STATUS_FAILED)
            ->update([
                'status' => OnecJob::STATUS_QUEUED,
                'error' => null,
                'claimed_at' => null,
                'heartbeat_at' => null,
                'finished_at' => null,
                'attempts' => 0,
                'queued_alert_sent_at' => null,
                'queued_at' => now(),
            ]);

        if ($retried === 0) {
            return false;
        }

        if ($job->batch !== null) {
            $this->collector->showQueued($telegram, $job);
        }

        return true;
    }

    /**
     * Run by the scheduler every minute.
     */
    public function supervise(): void
    {
        $this->returnSilentJobs();
        $this->warnAboutWaitingJobs();
    }

    /**
     * Delete the files of the questions older than
     * config('onec_helper.retention_days'); the questions, their texts and
     * the answers stay. The number of questions cleaned.
     */
    public function deleteOldFiles(): int
    {
        $questions = OnecQuestion::whereNull('files_deleted_at')
            ->where('created_at', '<', now()->subDays((int) config('onec_helper.retention_days')))
            ->get();

        foreach ($questions as $question) {
            OnecMessage::whereIn('batch_id', $question->batches()->pluck('id'))
                ->whereNotNull('file_path')
                ->whereNull('file_deleted_at')
                ->get()
                ->each(fn (OnecMessage $message) => $message->deleteFile());

            $question->update(['files_deleted_at' => now()]);
        }

        return $questions->count();
    }

    /**
     * A job in work without a heartbeat for config('onec_helper.heartbeat_timeout_minutes')
     * goes back to the queue, or fails after config('onec_helper.max_attempts').
     */
    private function returnSilentJobs(): void
    {
        $silentSince = now()->subMinutes((int) config('onec_helper.heartbeat_timeout_minutes'));

        $jobs = OnecJob::where('status', OnecJob::STATUS_PROCESSING)
            ->where('heartbeat_at', '<', $silentSince)
            ->get();

        foreach ($jobs as $job) {
            if ($job->attempts >= (int) config('onec_helper.max_attempts')) {
                $this->fail($job, OnecTexts::get('no_heartbeat', ['attempts' => $job->attempts]));

                continue;
            }

            OnecJob::whereKey($job->id)
                ->where('status', OnecJob::STATUS_PROCESSING)
                ->where('heartbeat_at', '<', $silentSince)
                ->update([
                    'status' => OnecJob::STATUS_QUEUED,
                    'queued_at' => now(),
                    'claimed_at' => null,
                    'heartbeat_at' => null,
                ]);
        }
    }

    /**
     * Once per job: a job the worker could take waits longer than
     * config('onec_helper.queued_alert_minutes').
     */
    private function warnAboutWaitingJobs(): void
    {
        $waitingSince = now()->subMinutes((int) config('onec_helper.queued_alert_minutes'));

        $jobs = OnecJob::queued()->whereNull('queued_alert_sent_at')->with('question')->orderBy('id')->get();

        foreach ($jobs as $job) {
            if (! $this->isAvailable($job) || $this->availableSince($job)->gt($waitingSince)) {
                continue;
            }

            $job->update(['queued_alert_sent_at' => now()]);

            SendOnecReplies::about($job, [
                OnecTexts::get('not_taken', ['title' => (string) $job->question->title]),
            ]);
        }
    }

    /**
     * A follow-up can be taken from the moment the job of its question
     * before it finished, not from the moment it was queued.
     */
    private function availableSince(OnecJob $job): Carbon
    {
        $since = $job->queued_at ?? $job->created_at ?? now();

        if ($job->kind === OnecJob::KIND_FOLLOWUP) {
            $finished = OnecJob::where('question_id', $job->question_id)
                ->whereKeyNot($job->id)
                ->max('finished_at');

            if ($finished !== null && Carbon::parse($finished)->gt($since)) {
                return Carbon::parse($finished);
            }
        }

        return $since;
    }

    /**
     * @param  Collection<int, OnecMessage>  $messages
     * @return array{0: list<array<string, mixed>>, 1: int} the messages and the last screenshot number
     */
    private function messages(Collection $messages, int $screen): array
    {
        $items = [];

        foreach ($messages->values() as $index => $message) {
            $item = ['position' => $index + 1, 'type' => $message->type];

            if ($message->hasFile()) {
                $item['screen'] = $message->is_screen ? ++$screen : null;
            }

            $item += [
                'text' => $message->text,
                'author' => $message->author,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'forwarded' => $message->forwarded,
            ];

            if ($message->hasFile()) {
                $item['file'] = [
                    'id' => $message->id,
                    'name' => $this->fileName($message, $item['screen']),
                    'mime' => $message->file_mime,
                    'size' => $message->file_size,
                ];
            }

            $items[] = $item;
        }

        return [$items, $screen];
    }

    /**
     * A photo has no name of its own in Telegram: it is named by its number.
     */
    private function fileName(OnecMessage $message, ?int $screen): string
    {
        if ($message->type !== OnecMessage::TYPE_PHOTO) {
            return (string) $message->file_name;
        }

        $extension = pathinfo((string) $message->file_path, PATHINFO_EXTENSION) ?: 'jpg';

        return 'screen-'.$screen.'.'.$extension;
    }
}
