<?php

namespace Tests\Feature;

use App\Models\OnecJob;
use App\Models\OnecMessage;
use App\Models\OnecQuestion;
use App\Services\TelegramServices\OnecHelperHandlers\OnecReplies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The API of the worker on the 1C server, /api/onec-helper, as the worker is
 * written against it.
 */
class OnecHelperApiTest extends OnecHelperTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpoints(): array
    {
        return [
            'claim' => ['POST', '/api/onec-helper/jobs/claim'],
            'file' => ['GET', '/api/onec-helper/files/1'],
            'heartbeat' => ['POST', '/api/onec-helper/jobs/1/heartbeat'],
            'result' => ['POST', '/api/onec-helper/jobs/1/result'],
            'fail' => ['POST', '/api/onec-helper/jobs/1/fail'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_a_request_without_the_token_is_refused(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
        $this->withToken('wrong-token')->json($method, $uri)->assertUnauthorized();
    }

    public function test_nobody_passes_while_the_token_is_not_configured(): void
    {
        config()->set('onec_helper.worker_token', null);

        $this->withToken('')->postJson('/api/onec-helper/jobs/claim')->assertUnauthorized();
    }

    public function test_claim_without_jobs_is_204_without_a_body(): void
    {
        $this->claim()->assertNoContent();
        $this->assertSame('', $this->claim()->getContent());
    }

    public function test_claim_gives_the_oldest_job_and_puts_it_in_work(): void
    {
        $first = $this->closedQuestion('Перше');
        $this->closedQuestion('Друге');

        $response = $this->claim()->assertOk();

        $this->assertStringContainsString('Перше', $response->getContent());
        $response->assertExactJson([
            'job' => ['id' => $first->id, 'kind' => 'question', 'attempt' => 1],
            'question' => [
                'id' => $first->question_id,
                'title' => 'Расхождение остатков по счёту 361 у ТОВ «Альфа»',
                'summary' => $this->structure['summary'],
                'entities' => $this->structure['entities'],
                'session_id' => null,
                'created_at' => $first->question->created_at->toIso8601String(),
            ],
            'messages' => [[
                'position' => 1,
                'type' => 'text',
                'text' => 'Перше',
                'author' => 'Максим Кубичка',
                'sent_at' => \Illuminate\Support\Carbon::createFromTimestamp(1793532300, 'Europe/Kyiv')->toIso8601String(),
                'forwarded' => false,
            ]],
            'followup' => null,
        ]);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $first->refresh();
        $this->assertSame(OnecJob::STATUS_PROCESSING, $first->status);
        $this->assertSame(1, $first->attempts);
        $this->assertNotNull($first->claimed_at);
        $this->assertNotNull($first->heartbeat_at);

        $this->assertSame('question', $this->claim()->assertOk()->json('job.kind'));
        $this->claim()->assertNoContent();
    }

    public function test_a_file_comes_with_its_content_type(): void
    {
        $this->sendDocument(100, 'Звіт.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $message = OnecMessage::sole();

        $response = $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => $message->id]))->assertOk();

        $this->assertSame('file-bytes', $response->streamedContent());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_a_file_that_is_not_there_is_404(): void
    {
        $this->sendText(100, 'Текст без файлу');
        $this->sendPhoto(101);
        $text = OnecMessage::where('type', 'text')->sole();
        $photo = OnecMessage::where('type', 'photo')->sole();

        $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => 999]))->assertNotFound();
        $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => $text->id]))->assertNotFound();

        $photo->deleteFile();
        $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => $photo->id]))->assertNotFound();
    }

    public function test_a_file_missing_from_this_containers_disk_is_404_and_logged(): void
    {
        $this->sendPhoto(100);
        $photo = OnecMessage::sole();
        Storage::disk('local')->delete($photo->file_path);
        Log::spy();

        $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => $photo->id]))->assertNotFound();

        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'not on the disk of this container'))->once();
    }

    public function test_heartbeat_keeps_the_job_alive_and_shows_its_note_at_most_every_30_seconds(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->edits = [];

        $this->travel(10)->seconds();
        $this->heartbeat($job, ['note' => 'смотрю РН-000123'])->assertNoContent();
        $this->assertTrue($job->fresh()->heartbeat_at->equalTo(now()->startOfSecond()));
        $this->assertCount(1, $this->edits);
        $this->assertSame(
            'Принял: «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 1 сообщение, 0 скриншотов. Разбираю: смотрю РН-000123',
            $this->edits[0]['text']
        );
        $this->assertSame($job->batch->service_message_id, $this->edits[0]['message_id']);

        $this->travel(10)->seconds();
        $this->heartbeat($job, ['note' => 'сверяю проводки'])->assertNoContent();
        $this->heartbeat($job, [])->assertNoContent();
        $this->assertCount(1, $this->edits);

        $this->travel(21)->seconds();
        $this->heartbeat($job, ['note' => 'сверяю проводки'])->assertNoContent();
        $this->assertCount(2, $this->edits);
    }

    public function test_heartbeat_of_a_job_not_in_work_is_refused(): void
    {
        $job = $this->closedQuestion('Питання');

        $this->heartbeat($job, [])->assertStatus(409);
        $this->heartbeat(999, [])->assertNotFound();
    }

    public function test_result_finishes_the_job_and_gives_the_session_to_the_question(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        $this->postResult($job, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        $job->refresh();
        $this->assertSame(OnecJob::STATUS_DONE, $job->status);
        $this->assertSame('Разбор.', $job->for_user);
        $this->assertSame('Для Анни.', $job->for_boss);
        $this->assertNotNull($job->finished_at);
        $this->assertSame('session-1', $job->question->session_id);
    }

    public function test_a_second_result_of_the_same_job_is_409(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postResult($job, ['session_id' => 's', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();
        $sent = count($this->sent);

        $this->postResult($job, ['session_id' => 's', 'for_user' => 'Ще раз.', 'for_boss' => 'Ще раз.'])->assertStatus(409);

        $this->assertSame('Разбор.', $job->fresh()->for_user);
        $this->assertCount($sent, $this->sent);
    }

    public function test_result_without_its_fields_is_422(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        $this->postResult($job, ['for_user' => 'Разбор.'])->assertStatus(422);
        $this->assertSame(OnecJob::STATUS_PROCESSING, $job->fresh()->status);
    }

    public function test_fail_tells_the_owner_in_reply_to_the_service_message(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->sent = [];
        $error = 'Не удалось подключиться к базе 1С: '.str_repeat('ошибка ', 100);

        $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.fail', ['job' => $job->id]), ['error' => $error])->assertNoContent();

        $this->assertSame(OnecJob::STATUS_FAILED, $job->fresh()->status);
        $this->assertSame($error, $job->fresh()->error);
        $this->assertCount(1, $this->sent);
        $this->assertSame('Не удалось разобрать: '.mb_substr($error, 0, 300), $this->sent[0]['text']);
        $this->assertSame($job->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);

        $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.fail', ['job' => $job->id]), ['error' => 'ещё'])->assertStatus(409);
    }

    public function test_a_followup_waits_for_the_session_of_its_question_and_for_its_job_in_work(): void
    {
        $questionJob = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        // A follow-up to the question being worked on, through its service message.
        $this->sendText(500, 'Уточнення', $this->replyTo($questionJob->batch->service_message_id));
        $this->sendPhoto(501);
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $followupJob = OnecJob::where('kind', 'followup')->sole();

        $this->claim()->assertNoContent();
        $this->supervise(10);
        $this->assertNull($followupJob->fresh()->queued_alert_sent_at);

        $this->postResult($questionJob, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        $response = $this->claim()->assertOk();
        $this->assertSame(['id' => $followupJob->id, 'kind' => 'followup', 'attempt' => 1], $response->json('job'));
        $this->assertSame('session-1', $response->json('question.session_id'));
        $this->assertSame(['Питання'], array_column($response->json('messages'), 'text'));
        $this->assertSame($followupJob->batch_id, $response->json('followup.id'));
        $this->assertSame([1, 2], array_column($response->json('followup.messages'), 'position'));
        $this->assertSame(['Уточнення', null], array_column($response->json('followup.messages'), 'text'));
    }

    public function test_the_screenshots_of_a_followup_are_numbered_on_from_the_question(): void
    {
        $this->sendPhoto(100);
        $this->sendPhoto(101);
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $questionJob = OnecJob::sole();
        $this->claim()->assertOk();
        $this->postResult($questionJob, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        $this->sendPhoto(500, 'Ось ще', $this->replyTo($this->nextBotMessageId));
        $this->sendDocument(501, 'scan.png', 'image/png');
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);

        $response = $this->claim()->assertOk();

        $this->assertSame([1, 2], array_column($response->json('messages'), 'screen'));
        $this->assertSame([3, 4], array_column($response->json('followup.messages'), 'screen'));
        $this->assertSame('screen-3.jpg', $response->json('followup.messages.0.file.name'));
    }

    public function test_a_followup_of_a_question_that_failed_fails_with_it(): void
    {
        $questionJob = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->sendText(500, 'Уточнення', $this->replyTo($questionJob->batch->service_message_id));
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);

        $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.fail', ['job' => $questionJob->id]), ['error' => 'Сбой'])->assertNoContent();

        $followupJob = OnecJob::where('kind', 'followup')->sole();
        $this->assertSame(OnecJob::STATUS_FAILED, $followupJob->status);
        $this->assertSame('Не удалось разобрать: вопрос, к которому это уточнение, не разобран', end($this->sent)['text']);
    }

    public function test_a_job_waiting_5_minutes_is_reported_once(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->sent = [];

        $this->supervise(4);
        $this->assertSame([], $this->sent);

        $this->supervise(2);
        $this->assertCount(1, $this->sent);
        $this->assertSame('Сервер 1С не забирает вопросы уже 5 минут: «Расхождение остатков по счёту 361 у ТОВ «Альфа»» в очереди.', $this->sent[0]['text']);
        $this->assertSame($job->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);

        $this->supervise(10);
        $this->assertCount(1, $this->sent);
    }

    public function test_a_job_without_a_heartbeat_goes_back_to_the_queue_then_fails(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        $this->supervise(14);
        $this->assertSame(OnecJob::STATUS_PROCESSING, $job->fresh()->status);

        $this->supervise(2);
        $this->assertSame(OnecJob::STATUS_QUEUED, $job->fresh()->status);

        $this->assertSame(2, $this->claim()->assertOk()->json('job.attempt'));
        $this->supervise(16);
        $this->assertSame(3, $this->claim()->assertOk()->json('job.attempt'));
        $this->sent = [];

        $this->supervise(16);

        $this->assertSame(OnecJob::STATUS_FAILED, $job->fresh()->status);
        $this->assertSame('Не удалось разобрать: сервер 1С не сообщал о работе 15 минут, попыток: 3', $this->sent[0]['text']);
        $this->claim()->assertNoContent();
    }

    public function test_the_files_of_old_questions_are_deleted_and_the_records_stay(): void
    {
        $this->sendPhoto(100);
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $job = OnecJob::sole();
        $this->claim()->assertOk();
        $this->postResult($job, ['session_id' => 's', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();
        $photo = OnecMessage::sole();

        $this->travel(29)->days();
        $this->artisan('onec-helper:delete-old-files')->assertSuccessful();
        Storage::disk('local')->assertExists($photo->file_path);

        $this->travel(2)->days();
        $this->artisan('onec-helper:delete-old-files')->assertSuccessful();

        Storage::disk('local')->assertMissing($photo->file_path);
        $this->assertNotNull($photo->fresh()->file_deleted_at);
        $this->assertNotNull(OnecQuestion::sole()->files_deleted_at);
        $this->assertSame('Разбор.', $job->fresh()->for_user);
        $this->withToken(self::TOKEN)->get(route('onec-helper.files.show', ['file' => $photo->id]))->assertNotFound();
    }

    public function test_split_keeps_a_short_text_whole_and_cuts_what_has_no_breaks(): void
    {
        $this->assertSame(['Короткий текст'], OnecReplies::split('Короткий текст'));
        $this->assertSame([], OnecReplies::split(''));

        $parts = OnecReplies::split(str_repeat('а', 5000));
        $this->assertSame([4096, 904], array_map('mb_strlen', $parts));

        $lines = OnecReplies::split(str_repeat("рядок\n", 1000));
        $this->assertCount(2, $lines);
        $this->assertStringEndsWith('рядок', $lines[0]);
    }

    private function heartbeat(OnecJob|int $job, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.heartbeat', ['job' => $job instanceof OnecJob ? $job->id : $job]), $body);
    }
}
