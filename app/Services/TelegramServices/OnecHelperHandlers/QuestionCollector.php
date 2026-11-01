<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Jobs\CloseOnecBatch;
use App\Jobs\RefreshOnecServiceMessage;
use App\Models\Bot;
use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Models\OnecMessage;
use App\Models\OnecQuestion;
use App\Services\ChatGPTServices\OnecQuestionStructurer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

/**
 * Gathers the messages of the owner of the 1C helper bot into a question, or
 * into a follow-up to one, under a service message with the counters and the
 * buttons "Разобрать сейчас" and "Отменить". A batch closes by the button or
 * after config('onec_helper.batch_seconds') without a new message, and becomes
 * a job for the worker on the 1C server.
 *
 * Telegram sends the parts of an album and quick messages at once, and the
 * queue handles them in parallel: the work on the batches of a chat goes under
 * one lock, so that two messages do not open two questions.
 */
class QuestionCollector
{
    public function __construct(
        protected OnecQuestionStructurer $structurer,
        protected OnecReplies $replies,
    ) {}

    /**
     * Add a message to the batch being gathered in the chat, the latest one;
     * with none, the message opens a question.
     *
     * @param  array<string, mixed>  $item  attributes of an OnecMessage
     */
    public function add(Bot $bot, Api $telegram, int $chatId, array $item): void
    {
        [$batch, $deferRefresh] = $this->locked($bot->id, $chatId, function () use ($bot, $telegram, $chatId, $item) {
            $batch = $this->openBatch($bot->id, $chatId) ?? $this->openQuestion($bot->id, $chatId);

            return [$batch, $this->append($telegram, $batch, $item)];
        });

        $this->scheduleAfter($batch, $deferRefresh);
    }

    /**
     * A reply to a message of the bot about the question: it goes to the batch
     * of the question still being gathered, or opens a follow-up to it. False
     * when the question takes no follow-up.
     *
     * @param  array<string, mixed>  $item  attributes of an OnecMessage
     */
    public function addToQuestion(Api $telegram, OnecQuestion $question, array $item): bool
    {
        $result = $this->locked($question->bot_id, $question->chat_id, function () use ($telegram, $question, $item) {
            $batch = $question->batches()->where('status', OnecBatch::STATUS_COLLECTING)->latest('id')->first();

            if ($batch === null) {
                if (! $question->takesFollowups()) {
                    return null;
                }

                $batch = OnecBatch::create([
                    'question_id' => $question->id,
                    'kind' => OnecBatch::KIND_FOLLOWUP,
                    'status' => OnecBatch::STATUS_COLLECTING,
                ]);
            }

            return [$batch, $this->append($telegram, $batch, $item)];
        });

        if ($result === null) {
            return false;
        }

        $this->scheduleAfter(...$result);

        return true;
    }

    /**
     * Whether a reply about the question can be taken: to its batch still
     * being gathered or as a follow-up.
     */
    public function takesReplyTo(OnecQuestion $question): bool
    {
        return $question->batches()->where('status', OnecBatch::STATUS_COLLECTING)->exists()
            || $question->takesFollowups();
    }

    /**
     * Close the batch: a question gets its title and summary from the model,
     * and the batch goes to the queue of the worker. With $onlyWhenIdle, as
     * the timer does, only a batch without a new message for the batch time.
     */
    public function close(Api $telegram, OnecBatch $batch, bool $onlyWhenIdle = false): bool
    {
        $question = $batch->question;

        $closed = $this->locked($question->bot_id, $question->chat_id, function () use ($batch, $onlyWhenIdle) {
            $batch->refresh();

            if ($batch->status !== OnecBatch::STATUS_COLLECTING || ($onlyWhenIdle && ! $this->isIdle($batch))) {
                return false;
            }

            $batch->update(['status' => OnecBatch::STATUS_CLOSED, 'closed_at' => now()]);

            return true;
        });

        if (! $closed) {
            return false;
        }

        // refresh() loaded the question anew.
        $question = $batch->question;

        // The model answers in seconds: outside the lock, the next message of
        // the chat opens a new question meanwhile.
        if (! $batch->isFollowup()) {
            $question->update($this->structurer->structure($batch->messages()->get()));
        }

        OnecJob::create([
            'question_id' => $question->id,
            'batch_id' => $batch->id,
            'kind' => $batch->isFollowup() ? OnecJob::KIND_FOLLOWUP : OnecJob::KIND_QUESTION,
            'status' => OnecJob::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $this->edit($telegram, $batch, $this->acceptedText($batch));

        return true;
    }

    /**
     * Cancel the batch being gathered and delete its files.
     */
    public function cancel(Api $telegram, OnecBatch $batch): bool
    {
        $question = $batch->question;

        $cancelled = $this->locked($question->bot_id, $question->chat_id, function () use ($batch) {
            $batch->refresh();

            if ($batch->status !== OnecBatch::STATUS_COLLECTING) {
                return false;
            }

            $batch->update(['status' => OnecBatch::STATUS_CANCELLED, 'closed_at' => now()]);

            return true;
        });

        if (! $cancelled) {
            return false;
        }

        $batch->messages()->get()->each(fn (OnecMessage $message) => $message->deleteFile());

        $this->edit($telegram, $batch, OnecTexts::get($batch->isFollowup() ? 'cancelled_followup' : 'cancelled'));

        return true;
    }

    /**
     * Show the counters of a batch being gathered. Telegram is not edited
     * more often than config('onec_helper.edit_interval_seconds'): false when
     * it is too soon, and the caller edits later. $force edits anyway.
     */
    public function refresh(Api $telegram, OnecBatch $batch, bool $force = false): bool
    {
        if ($batch->status !== OnecBatch::STATUS_COLLECTING || $batch->service_message_id === null) {
            return true;
        }

        $interval = (int) config('onec_helper.edit_interval_seconds');

        if (! $force && $batch->service_edited_at !== null && $batch->service_edited_at->gt(now()->subSeconds($interval))) {
            return false;
        }

        $batch->update(['service_edited_at' => now()]);
        $this->edit($telegram, $batch, $this->collectingText($batch), $this->keyboard($batch));

        return true;
    }

    /**
     * Show in the service message what the worker says it does.
     */
    public function showNote(Api $telegram, OnecBatch $batch, string $note): void
    {
        $this->edit($telegram, $batch, $this->acceptedText($batch, $note));
    }

    public function collectingText(OnecBatch $batch): string
    {
        [$messages, $screens] = $this->counts($batch);

        $counts = OnecTexts::messages($messages).($screens > 0 ? ', '.OnecTexts::screens($screens) : '');

        return OnecTexts::get($batch->isFollowup() ? 'collecting_followup' : 'collecting', ['counts' => $counts]);
    }

    public function acceptedText(OnecBatch $batch, ?string $note = null): string
    {
        [$messages, $screens] = $this->counts($batch);

        $key = $note === null ? 'accepted' : 'in_work';

        return OnecTexts::get($batch->isFollowup() ? $key.'_followup' : $key, [
            'title' => (string) $batch->question->title,
            'messages' => OnecTexts::messages($messages),
            'screens' => OnecTexts::screens($screens),
            'note' => (string) $note,
        ]);
    }

    /**
     * @return list<list<array{text: string, callback_data: string}>>
     */
    private function keyboard(OnecBatch $batch): array
    {
        return [[
            ['text' => OnecTexts::get('analyze_now'), 'callback_data' => 'onec_close_'.$batch->id],
            ['text' => OnecTexts::get('cancel'), 'callback_data' => 'onec_cancel_'.$batch->id],
        ]];
    }

    /**
     * Store the message; the first one of the batch gets the service message
     * in reply. True when the counters have to be shown later.
     *
     * @param  array<string, mixed>  $item
     */
    private function append(Api $telegram, OnecBatch $batch, array $item): bool
    {
        OnecMessage::firstOrCreate(
            ['batch_id' => $batch->id, 'telegram_message_id' => $item['telegram_message_id']],
            $item
        );

        $batch->update(['last_message_at' => now()]);

        if ($batch->service_message_id !== null) {
            return ! $this->refresh($telegram, $batch);
        }

        $sent = $telegram->sendMessage([
            'chat_id' => $batch->question->chat_id,
            'text' => $this->collectingText($batch),
            'reply_markup' => json_encode(['inline_keyboard' => $this->keyboard($batch)]),
            'reply_parameters' => json_encode([
                'message_id' => $item['telegram_message_id'],
                'allow_sending_without_reply' => true,
            ]),
        ]);

        $batch->update(['service_message_id' => (int) $sent->getMessageId(), 'service_edited_at' => now()]);
        $this->replies->remember($batch, (int) $batch->service_message_id);

        return false;
    }

    /**
     * Dispatched outside the lock: on the sync queue of the tests a job runs
     * at once and takes the lock itself.
     */
    private function scheduleAfter(OnecBatch $batch, bool $deferRefresh): void
    {
        CloseOnecBatch::dispatch($batch->id)
            ->delay(now()->addSeconds((int) config('onec_helper.batch_seconds')));

        if ($deferRefresh) {
            RefreshOnecServiceMessage::dispatch($batch->id)
                ->delay(now()->addSeconds((int) config('onec_helper.edit_interval_seconds')));
        }
    }

    /**
     * The timer job comes the batch time after the message that dispatched
     * it; a second of leeway for the time stored without its fraction.
     */
    private function isIdle(OnecBatch $batch): bool
    {
        $quiet = max(0, (int) config('onec_helper.batch_seconds') - 1);

        return $batch->last_message_at === null || $batch->last_message_at->lte(now()->subSeconds($quiet));
    }

    private function openBatch(int $botId, int $chatId): ?OnecBatch
    {
        return OnecBatch::where('status', OnecBatch::STATUS_COLLECTING)
            ->whereHas('question', fn ($query) => $query->where('bot_id', $botId)->where('chat_id', $chatId))
            ->latest('id')
            ->first();
    }

    private function openQuestion(int $botId, int $chatId): OnecBatch
    {
        $question = OnecQuestion::create(['bot_id' => $botId, 'chat_id' => $chatId]);

        return OnecBatch::create([
            'question_id' => $question->id,
            'kind' => OnecBatch::KIND_QUESTION,
            'status' => OnecBatch::STATUS_COLLECTING,
        ]);
    }

    /**
     * @return array{0: int, 1: int} messages and screenshots
     */
    private function counts(OnecBatch $batch): array
    {
        return [
            $batch->messages()->count(),
            $batch->messages()->where('is_screen', true)->count(),
        ];
    }

    /**
     * Telegram drops the buttons of a message edited without reply_markup.
     *
     * @param  list<list<array<string, string>>>  $keyboard
     */
    private function edit(Api $telegram, OnecBatch $batch, string $text, array $keyboard = []): void
    {
        try {
            $telegram->editMessageText([
                'chat_id' => $batch->question->chat_id,
                'message_id' => $batch->service_message_id,
                'text' => $text,
            ] + ($keyboard === [] ? [] : ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]));
        } catch (\Throwable $e) {
            // Telegram refuses an edit that changes nothing, and one of a
            // message the owner deleted.
            Log::info('Could not edit the service message of the 1C helper bot', [
                'batch' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(int $botId, int $chatId, callable $callback): mixed
    {
        return Cache::lock("onec-helper:chat:{$botId}:{$chatId}", 60)->block(30, $callback);
    }
}
