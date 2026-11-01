<?php

namespace Tests\Feature;

use App\Models\OnecJob;

/**
 * A failed job is analysed again only when the owner asks: by the button
 * "Повторить" under the failure, or by onec-helper:retry for a failure sent
 * without it.
 */
class OnecHelperRetryTest extends OnecHelperTestCase
{
    public function test_a_failure_of_the_worker_comes_with_the_retry_button(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        $this->postFail($job, "claude: success You've hit your 5-hour limit")->assertNoContent();

        $failure = end($this->sent);
        $this->assertSame("Не удалось разобрать: claude: success You've hit your 5-hour limit", $failure['text']);
        $this->assertSame($job->batch->service_message_id, json_decode($failure['reply_parameters'], true)['message_id']);
        $this->assertSame(
            [[['text' => 'Повторить', 'callback_data' => 'onec_retry_'.$job->id]]],
            json_decode($failure['reply_markup'], true)['inline_keyboard']
        );
    }

    public function test_the_failure_of_a_silent_worker_comes_with_the_retry_button(): void
    {
        $job = $this->closedQuestion('Питання');
        foreach (range(1, 3) as $attempt) {
            $this->claim()->assertOk();
            $this->supervise(16);
        }

        $failure = end($this->sent);
        $this->assertSame(OnecJob::STATUS_FAILED, $job->fresh()->status);
        $this->assertSame('Не удалось разобрать: сервер 1С не сообщал о работе 15 минут, попыток: 3', $failure['text']);
        $this->assertSame(
            [[['text' => 'Повторить', 'callback_data' => 'onec_retry_'.$job->id]]],
            json_decode($failure['reply_markup'], true)['inline_keyboard']
        );
    }

    public function test_the_answer_comes_without_buttons(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($job, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        $this->assertCount(2, $this->sent);
        $this->assertArrayNotHasKey('reply_markup', $this->sent[0]);
        $this->assertArrayNotHasKey('reply_markup', $this->sent[1]);
    }

    public function test_the_button_puts_the_failed_job_in_the_queue_as_a_new_one(): void
    {
        $this->freezeSecond();
        $job = $this->closedQuestion('Питання');
        $this->supervise(6);
        $this->claim()->assertOk();
        $this->postFail($job, 'claude: limit')->assertNoContent();
        $failed = $job->fresh();
        $this->assertNotNull($failed->queued_alert_sent_at);
        $this->assertNotNull($failed->claimed_at);
        $this->assertSame(1, $failed->attempts);
        $failureMessageId = $this->nextBotMessageId;
        $this->edits = [];
        $this->sent = [];
        $this->travel(10)->minutes();

        $this->press('onec_retry_'.$job->id, $failureMessageId);

        $retried = $job->fresh();
        $this->assertSame(OnecJob::STATUS_QUEUED, $retried->status);
        $this->assertNull($retried->error);
        $this->assertNull($retried->claimed_at);
        $this->assertNull($retried->heartbeat_at);
        $this->assertNull($retried->finished_at);
        $this->assertSame(0, $retried->attempts);
        $this->assertNull($retried->queued_alert_sent_at);
        $this->assertTrue($retried->queued_at->equalTo(now()));

        // The button is dropped from the failure, the service message shows the queue again.
        $this->assertSame([['chat_id' => self::OWNER, 'message_id' => $failureMessageId]], $this->markupEdits);
        $this->assertCount(1, $this->edits);
        $this->assertSame($job->batch->service_message_id, $this->edits[0]['message_id']);
        $this->assertSame('Принял: «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 1 сообщение, 0 скриншотов. В очереди на разбор.', $this->edits[0]['text']);
        $this->assertArrayNotHasKey('reply_markup', $this->edits[0]);
        $this->assertSame('Повторяю', end($this->answered)['text']);
        $this->assertSame([], $this->sent);

        $response = $this->claim()->assertOk();
        $this->assertSame(['id' => $job->id, 'kind' => 'question', 'attempt' => 1], $response->json('job'));
    }

    public function test_a_job_retried_already_or_analysed_is_not_changed(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postFail($job, 'claude: limit')->assertNoContent();
        $failureMessageId = $this->nextBotMessageId;
        $this->press('onec_retry_'.$job->id, $failureMessageId);
        $this->claim()->assertOk();
        $inWork = $job->fresh()->getAttributes();
        $this->edits = [];
        $this->markupEdits = [];

        $this->press('onec_retry_'.$job->id, $failureMessageId);

        $this->assertSame($inWork, $job->fresh()->getAttributes());
        $this->assertSame('Уже повторено', end($this->answered)['text']);
        $this->assertSame([['chat_id' => self::OWNER, 'message_id' => $failureMessageId]], $this->markupEdits);
        $this->assertSame([], $this->edits);

        $this->postResult($job, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();
        $done = $job->fresh()->getAttributes();

        $this->press('onec_retry_'.$job->id, $failureMessageId);

        $this->assertSame($done, $job->fresh()->getAttributes());
        $this->assertSame(OnecJob::STATUS_DONE, $job->fresh()->status);
        $this->assertSame('Уже повторено', end($this->answered)['text']);
    }

    public function test_a_job_that_is_not_the_latest_of_its_batch_is_not_retried(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postFail($job, 'claude: limit')->assertNoContent();
        $failureMessageId = $this->nextBotMessageId;
        $later = OnecJob::create([
            'question_id' => $job->question_id,
            'batch_id' => $job->batch_id,
            'kind' => OnecJob::KIND_QUESTION,
            'status' => OnecJob::STATUS_FAILED,
            'error' => 'claude: limit',
            'finished_at' => now(),
        ]);
        $before = $job->fresh()->getAttributes();
        $this->edits = [];

        $this->press('onec_retry_'.$job->id, $failureMessageId);

        $this->assertSame($before, $job->fresh()->getAttributes());
        $this->assertSame(OnecJob::STATUS_FAILED, $later->fresh()->status);
        $this->assertSame('Уже повторено', end($this->answered)['text']);
        $this->assertSame([], $this->edits);
        $this->claim()->assertNoContent();
    }

    public function test_the_followups_that_failed_with_their_question_are_retried_by_their_own_buttons(): void
    {
        $questionJob = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->sendText(500, 'Уточнення', $this->replyTo($questionJob->batch->service_message_id));
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $followupJob = OnecJob::where('kind', 'followup')->sole();
        $this->sent = [];

        $this->postFail($questionJob, 'claude: limit')->assertNoContent();

        $this->assertCount(2, $this->sent);
        $questionFailureId = $this->nextBotMessageId - 1;
        $followupFailureId = $this->nextBotMessageId;
        $this->assertSame('Не удалось разобрать: вопрос, к которому это уточнение, не разобран', $this->sent[1]['text']);
        $this->assertSame($followupJob->batch->service_message_id, json_decode($this->sent[1]['reply_parameters'], true)['message_id']);
        $this->assertSame('onec_retry_'.$followupJob->id, json_decode($this->sent[1]['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);

        $this->press('onec_retry_'.$questionJob->id, $questionFailureId);

        $this->assertSame(OnecJob::STATUS_QUEUED, $questionJob->fresh()->status);
        $this->assertSame(OnecJob::STATUS_FAILED, $followupJob->fresh()->status);
        $this->edits = [];

        $this->press('onec_retry_'.$followupJob->id, $followupFailureId);

        $this->assertSame(OnecJob::STATUS_QUEUED, $followupJob->fresh()->status);
        $this->assertSame('Повторяю', end($this->answered)['text']);
        $this->assertSame($followupJob->batch->service_message_id, $this->edits[0]['message_id']);
        $this->assertSame('Принял уточнение к «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 1 сообщение, 0 скриншотов. В очереди на разбор.', $this->edits[0]['text']);

        // The follow-up waits for the session the question gets once analysed.
        $this->assertSame($questionJob->id, $this->claim()->assertOk()->json('job.id'));
        $this->claim()->assertNoContent();
        $this->postResult($questionJob, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();
        $this->assertSame($followupJob->id, $this->claim()->assertOk()->json('job.id'));
    }

    public function test_the_command_retries_a_failure_sent_without_the_button(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postFail($job, 'claude: limit')->assertNoContent();
        $this->sent = [];
        $this->edits = [];

        $this->artisan('onec-helper:retry', ['job' => $job->id])->assertSuccessful();

        $this->assertSame(OnecJob::STATUS_QUEUED, $job->fresh()->status);
        $this->assertSame(0, $job->fresh()->attempts);
        $this->assertNull($job->fresh()->error);
        $this->assertSame('Принял: «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 1 сообщение, 0 скриншотов. В очереди на разбор.', $this->edits[0]['text']);
        $this->assertCount(1, $this->sent);
        $this->assertSame('Повторяю разбор.', $this->sent[0]['text']);
        $this->assertSame($job->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertArrayNotHasKey('reply_markup', $this->sent[0]);

        // A second run, and a job that is not there, change nothing.
        $this->sent = [];
        $this->artisan('onec-helper:retry', ['job' => $job->id])->assertFailed();
        $this->artisan('onec-helper:retry', ['job' => $job->id + 100])->assertFailed();
        $this->assertSame([], $this->sent);
        $this->assertSame(OnecJob::STATUS_QUEUED, $job->fresh()->status);
    }

    public function test_someone_else_cannot_press_retry(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postFail($job, 'claude: limit')->assertNoContent();
        $failureMessageId = $this->nextBotMessageId;
        $before = $job->fresh()->getAttributes();
        $this->answered = [];
        $this->edits = [];

        $this->press('onec_retry_'.$job->id, $failureMessageId, 777);

        $this->assertSame($before, $job->fresh()->getAttributes());
        $this->assertSame([], $this->answered);
        $this->assertSame([], $this->markupEdits);
        $this->assertSame([], $this->edits);
    }
}
