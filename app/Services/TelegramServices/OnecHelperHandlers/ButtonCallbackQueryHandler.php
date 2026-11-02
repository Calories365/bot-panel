<?php

namespace App\Services\TelegramServices\OnecHelperHandlers;

use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Models\OnecQuestion;
use App\Services\TelegramServices\CaloriesHandlers\CallbackQueryHandlers\CallbackQueryHandlerInterface;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\CallbackQuery;

/**
 * The buttons of the 1C helper bot: onec_close_{batch} closes the question or
 * follow-up at once, onec_cancel_{batch} cancels it, onec_window_{batch}
 * closes a question to open it on the 1C server, all under the service
 * message; onec_retry_{job} under a failure puts the failed job in the queue
 * again; onec_resume_{question} under an answer opens the session of its
 * analysis on the server.
 */
class ButtonCallbackQueryHandler implements CallbackQueryHandlerInterface
{
    public bool $blockAble = false;

    public function __construct(
        protected QuestionCollector $collector,
        protected OnecJobBoard $board,
    ) {}

    public function handle($bot, $telegram, $callbackQuery, $botUser)
    {
        $parts = explode('_', (string) $callbackQuery->getData());
        $chatId = (int) $callbackQuery->getMessage()->getChat()->getId();

        if (($parts[1] ?? null) === 'retry') {
            $this->retry($bot->id, $telegram, $callbackQuery, $chatId, (int) ($parts[2] ?? 0));

            return;
        }

        if (($parts[1] ?? null) === 'resume') {
            $this->resume($bot->id, $telegram, $callbackQuery, $chatId, (int) ($parts[2] ?? 0));

            return;
        }

        // Telegram keeps the button spinning until the answer, and closing
        // waits for the model.
        $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

        $batch = OnecBatch::whereKey((int) ($parts[2] ?? 0))
            ->whereHas('question', fn ($query) => $query->where('bot_id', $bot->id)->where('chat_id', $chatId))
            ->first();

        if ($batch === null) {
            return;
        }

        match ($parts[1] ?? null) {
            'close' => $this->collector->close($telegram, $batch),
            'cancel' => $this->collector->cancel($telegram, $batch),
            // A follow-up has no such button.
            'window' => $batch->isFollowup() ? null : $this->collector->close($telegram, $batch, openOnServer: true),
            default => null,
        };
    }

    /**
     * The button stays under the answer: the session can be opened again.
     */
    private function resume(int $botId, Api $telegram, CallbackQuery $callbackQuery, int $chatId, int $questionId): void
    {
        $question = OnecQuestion::whereKey($questionId)->where('bot_id', $botId)->where('chat_id', $chatId)->first();

        if ($question === null) {
            $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

            return;
        }

        $queued = $this->board->resume($question, (int) $callbackQuery->getMessage()->getMessageId());

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => OnecTexts::get(match (true) {
                $queued => 'opening_on_server',
                $question->fresh()->session_id === null => 'no_session',
                default => 'already_opening',
            }),
        ]);
    }

    /**
     * The button is spent whether the job went back to the queue or was
     * taken again before: it is dropped from the failure message.
     */
    private function retry(int $botId, Api $telegram, CallbackQuery $callbackQuery, int $chatId, int $jobId): void
    {
        $job = OnecJob::whereKey($jobId)
            ->whereHas('question', fn ($query) => $query->where('bot_id', $botId)->where('chat_id', $chatId))
            ->first();

        if ($job === null) {
            $telegram->answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

            return;
        }

        $retried = $this->board->retry($telegram, $job);

        try {
            // Without reply_markup Telegram drops the buttons of the message.
            $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $callbackQuery->getMessage()->getMessageId(),
            ]);
        } catch (\Throwable $e) {
            // Telegram refuses the edit of a message without buttons already.
            Log::info('Could not drop the retry button of the 1C helper bot', [
                'job' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }

        $telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
            'text' => OnecTexts::get($retried ? 'retrying' : 'already_retried'),
        ]);
    }
}
