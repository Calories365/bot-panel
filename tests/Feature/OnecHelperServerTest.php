<?php

namespace Tests\Feature;

use App\Jobs\SendOnecReplies;
use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Models\OnecQuestion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * "Открыть на сервере" and "Продолжить на сервере": the worker on the 1C
 * server opens a session of Claude there for the owner, with the messages of
 * a question gathered just now (a job "window") or with a copy of the session
 * that analysed a question (a job "resume"), and gives back where it is.
 */
class OnecHelperServerTest extends OnecHelperTestCase
{
    private const TITLE = 'Расхождение остатков по счёту 361 у ТОВ «Альфа»';

    public function test_a_followup_keeps_its_two_buttons(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->sent = [];

        $this->sendText(500, 'Уточнення', $this->replyTo($answerId));

        $followup = $this->latestBatch();
        $this->assertSame([[
            ['text' => 'Разобрать сейчас', 'callback_data' => 'onec_close_'.$followup->id],
            ['text' => 'Отменить', 'callback_data' => 'onec_cancel_'.$followup->id],
        ]], json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard']);
    }

    public function test_the_counters_keep_the_button_open_on_server(): void
    {
        $this->sendText(100, 'Перше');
        $this->travel(4)->seconds();
        $this->sendText(101, 'Друге');

        $this->assertSame('Собираю вопрос: 2 сообщения…', end($this->edits)['text']);
        $keyboard = json_decode(end($this->edits)['reply_markup'], true)['inline_keyboard'];
        $this->assertSame('onec_window_'.$this->latestBatch()->id, $keyboard[1][0]['callback_data']);
    }

    public function test_open_on_server_closes_the_question_as_a_job_window(): void
    {
        $this->sendText(100, 'Добрий день! Чому не сходиться сальдо по 361?');
        $this->sendPhoto(101);
        $batch = $this->latestBatch();
        $this->edits = [];

        $this->press('onec_window_'.$batch->id, $batch->service_message_id);

        $this->assertSame(OnecBatch::STATUS_CLOSED, $batch->fresh()->status);
        $job = OnecJob::sole();
        $this->assertSame(OnecJob::KIND_WINDOW, $job->kind);
        $this->assertSame(OnecJob::STATUS_QUEUED, $job->status);
        $this->assertSame($batch->id, $job->batch_id);

        // The title and summary come from the model, as for an analysis.
        $question = OnecQuestion::sole();
        $this->assertSame(self::TITLE, $question->title);
        $this->assertStringStartsWith('Главбух спрашивает', $question->summary);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/chat/completions'));

        $this->assertCount(1, $this->edits);
        $this->assertSame($batch->service_message_id, $this->edits[0]['message_id']);
        $this->assertSame('Принял: «'.self::TITLE.'». 2 сообщения, 1 скриншот. Открываю на сервере.', $this->edits[0]['text']);
        $this->assertArrayNotHasKey('reply_markup', $this->edits[0]);
        $this->assertCount(1, $this->answered);
    }

    public function test_open_on_server_of_a_question_closed_already_changes_nothing(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();
        $this->closeByTimer($batch);
        $this->assertSame(OnecJob::KIND_QUESTION, OnecJob::sole()->kind);
        $this->edits = [];

        $this->press('onec_window_'.$batch->id, $batch->service_message_id);

        $this->assertSame(OnecJob::KIND_QUESTION, OnecJob::sole()->kind);
        $this->assertSame([], $this->edits);

        $this->sendText(200, 'Друге питання');
        $second = $this->latestBatch();
        $this->press('onec_close_'.$second->id, $second->service_message_id);
        $this->press('onec_window_'.$second->id, $second->service_message_id);

        $this->assertSame(OnecJob::KIND_QUESTION, OnecJob::where('batch_id', $second->id)->sole()->kind);

        $this->sendText(300, 'Третє питання');
        $third = $this->latestBatch();
        $this->press('onec_window_'.$third->id, $third->service_message_id);
        $this->press('onec_window_'.$third->id, $third->service_message_id);
        $this->press('onec_close_'.$third->id, $third->service_message_id);

        $this->assertSame(OnecJob::KIND_WINDOW, OnecJob::where('batch_id', $third->id)->sole()->kind);
    }

    public function test_open_on_server_under_a_followup_does_nothing(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->sendText(500, 'Уточнення', $this->replyTo($answerId));
        $followup = $this->latestBatch();

        $this->press('onec_window_'.$followup->id, $followup->service_message_id);

        $this->assertSame(OnecBatch::STATUS_COLLECTING, $followup->fresh()->status);
        $this->assertSame(0, OnecJob::where('batch_id', $followup->id)->count());
    }

    public function test_someone_else_cannot_open_a_question_on_the_server(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();

        $this->press('onec_window_'.$batch->id, $batch->service_message_id, 777);

        $this->assertSame(OnecBatch::STATUS_COLLECTING, $batch->fresh()->status);
        $this->assertSame(0, OnecJob::count());
        $this->assertSame([], $this->answered);
    }

    public function test_the_worker_gets_a_window_with_the_messages_of_the_question(): void
    {
        $this->sendText(100, 'Перше');
        $this->sendPhoto(101);
        $job = $this->windowQuestion();

        $response = $this->claim()->assertOk();

        $this->assertSame(['id' => $job->id, 'kind' => 'window', 'attempt' => 1], $response->json('job'));
        $this->assertSame(self::TITLE, $response->json('question.title'));
        $this->assertNull($response->json('question.session_id'));
        $this->assertSame(['Перше', null], array_column($response->json('messages'), 'text'));
        $this->assertSame([null, 1], array_map(fn ($message) => $message['screen'] ?? null, $response->json('messages')));
        $this->assertNull($response->json('followup'));
        $this->assertArrayHasKey('followup', $response->json());
    }

    public function test_a_job_waits_for_the_title_of_its_question(): void
    {
        $job = $this->windowQuestion();
        $job->question->update(['title' => null]);

        $this->claim()->assertNoContent();

        $job->question->update(['title' => 'Питання']);
        $this->assertSame($job->id, $this->claim()->assertOk()->json('job.id'));
    }

    public function test_the_note_of_the_worker_is_added_to_the_service_message_of_a_window(): void
    {
        $job = $this->windowQuestion();
        $this->claim()->assertOk();
        $this->edits = [];

        $this->heartbeat($job, ['note' => 'запускаю сессию'])->assertNoContent();

        $this->assertSame($job->batch->service_message_id, $this->edits[0]['message_id']);
        $this->assertSame('Принял: «'.self::TITLE.'». 1 сообщение, 0 скриншотов. Открываю на сервере: запускаю сессию', $this->edits[0]['text']);
    }

    public function test_the_result_of_a_window_is_kept_and_the_owner_gets_the_command_to_attach(): void
    {
        $job = $this->windowQuestion();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($job, $this->serverSession())->assertNoContent();

        $job->refresh();
        $this->assertSame(OnecJob::STATUS_DONE, $job->status);
        $this->assertSame('380c2274-6f3a-4c1e-9d55-0b6a1f2e7c11', $job->session_id);
        $this->assertSame('380c2274', $job->attach_id);
        $this->assertSame('questions/2026-11-02-raskhozhdenie', $job->folder);
        $this->assertSame('C:\agents\onec', $job->project_dir);
        $this->assertTrue($job->window_opened);
        $this->assertNotNull($job->finished_at);
        $this->assertNull($job->for_user);
        $this->assertNull($job->question->fresh()->session_id);

        $this->assertCount(1, $this->sent);
        $this->assertSame(
            "Сессия Claude на сервере запущена, окно открыто.\nПодключиться: <code>claude attach 380c2274</code>\nВсе сессии: <code>claude agents</code>",
            $this->sent[0]['text']
        );
        $this->assertSame('HTML', $this->sent[0]['parse_mode']);
        $this->assertSame($job->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertArrayNotHasKey('reply_markup', $this->sent[0]);

        $this->postResult($job, $this->serverSession(['attach_id' => 'other']))->assertStatus(409);
        $this->assertSame('380c2274', $job->fresh()->attach_id);
        $this->assertCount(1, $this->sent);
    }

    public function test_a_window_that_did_not_open_is_told_with_the_command_to_attach(): void
    {
        $job = $this->windowQuestion();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($job, $this->serverSession(['window' => false, 'attach_id' => 'a<b>&c']))->assertNoContent();

        $this->assertFalse($job->fresh()->window_opened);
        $this->assertSame('a<b>&c', $job->fresh()->attach_id);
        $this->assertSame(
            'Сессия Claude на сервере запущена, окно открыть не удалось. Подключиться: <code>claude attach a&lt;b&gt;&amp;c</code>',
            $this->sent[0]['text']
        );
        $this->assertSame('HTML', $this->sent[0]['parse_mode']);
    }

    public function test_the_result_of_a_window_is_validated_by_its_kind(): void
    {
        $job = $this->windowQuestion();
        $this->claim()->assertOk();

        // The fields of an analysis are not those of a window.
        $this->postResult($job, ['session_id' => 's', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attach_id', 'project_dir', 'window']);

        foreach (['session_id', 'attach_id', 'project_dir', 'window'] as $field) {
            $this->postResult($job, Arr::except($this->serverSession(), $field))->assertStatus(422)->assertJsonValidationErrors([$field]);
        }

        $this->postResult($job, $this->serverSession(['window' => 'true']))->assertStatus(422)->assertJsonValidationErrors(['window']);
        $this->postResult($job, $this->serverSession(['window' => 1]))->assertStatus(422)->assertJsonValidationErrors(['window']);
        $this->postResult($job, $this->serverSession(['folder' => 5]))->assertStatus(422)->assertJsonValidationErrors(['folder']);
        $this->assertSame(OnecJob::STATUS_PROCESSING, $job->fresh()->status);

        // The folder may be null or absent.
        $this->postResult($job, Arr::except($this->serverSession(), 'folder'))->assertNoContent();
        $this->assertNull($job->fresh()->folder);
    }

    public function test_an_analysis_still_needs_its_answer(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();

        $this->postResult($job, $this->serverSession())->assertStatus(422)->assertJsonValidationErrors(['for_user']);
        $this->assertSame(OnecJob::STATUS_PROCESSING, $job->fresh()->status);
    }

    public function test_a_question_opened_on_the_server_takes_no_followup(): void
    {
        $job = $this->windowQuestion();
        $serviceMessageId = $job->batch->service_message_id;

        // Before the worker tells where the session is.
        $this->sendText(500, 'Уточнення', $this->replyTo($serviceMessageId));
        $this->assertSame('Этот вопрос открыт на сервере, продолжай там.', end($this->sent)['text']);
        $this->assertArrayNotHasKey('parse_mode', end($this->sent));

        $this->claim()->assertOk();
        $this->postResult($job, $this->serverSession())->assertNoContent();
        $resultMessageId = $this->nextBotMessageId;

        foreach ([$serviceMessageId, $resultMessageId] as $index => $messageId) {
            $this->sendText(501 + $index, 'Уточнення', $this->replyTo($messageId));

            $answer = end($this->sent);
            $this->assertSame('Этот вопрос открыт на сервере, продолжай там: <code>claude attach 380c2274</code>', $answer['text']);
            $this->assertSame('HTML', $answer['parse_mode']);
            $this->assertSame(501 + $index, json_decode($answer['reply_parameters'], true)['message_id']);
        }

        $this->assertSame(1, OnecBatch::count());
        $this->assertSame(1, OnecJob::count());
    }

    public function test_the_answer_has_the_button_continue_on_server_on_its_first_message(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->sent = [];
        $forUser = implode("\n\n", array_fill(0, 3, str_repeat('слово ', 400)));

        $this->postResult($job, ['session_id' => 'analysis-session', 'for_user' => $forUser, 'for_boss' => 'Для Анни.'])->assertNoContent();

        $this->assertGreaterThan(2, count($this->sent));
        $this->assertStringStartsWith('Разбор:', $this->sent[0]['text']);
        $this->assertSame(
            [[['text' => 'Продолжить на сервере', 'callback_data' => 'onec_resume_'.$job->question_id]]],
            json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard']
        );
        foreach (array_slice($this->sent, 1) as $message) {
            $this->assertArrayNotHasKey('reply_markup', $message);
        }

        // The answer of a follow-up has it too.
        $this->sendText(500, 'Уточнення', $this->replyTo($this->nextBotMessageId));
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $followupJob = OnecJob::where('kind', OnecJob::KIND_FOLLOWUP)->sole();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($followupJob, ['session_id' => 'analysis-session', 'for_user' => 'Ще розбір.'])->assertNoContent();

        $this->assertSame("Разбор:\nЩе розбір.", $this->sent[0]['text']);
        $this->assertSame($followupJob->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertSame('onec_resume_'.$job->question_id, json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);
    }

    public function test_continue_on_server_queues_a_resume_without_a_batch(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->markupEdits = [];

        $this->press('onec_resume_'.$job->question_id, $answerId);

        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->assertSame($job->question_id, $resume->question_id);
        $this->assertNull($resume->batch_id);
        $this->assertSame($answerId, $resume->reply_to_message_id);
        $this->assertSame(OnecJob::STATUS_QUEUED, $resume->status);
        $this->assertSame('Открываю на сервере', end($this->answered)['text']);
        // The button stays.
        $this->assertSame([], $this->markupEdits);
    }

    public function test_continue_on_server_again_while_the_resume_waits_or_works_changes_nothing(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();

        $this->press('onec_resume_'.$job->question_id, $answerId);
        $this->assertSame(1, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertSame('Уже открываю', end($this->answered)['text']);

        $this->claim()->assertOk();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $this->assertSame(1, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertSame('Уже открываю', end($this->answered)['text']);

        // Once the window is open, it can be opened again.
        $this->postResult($resume, $this->serverSession(['folder' => null]))->assertNoContent();
        $this->press('onec_resume_'.$job->question_id, $answerId);

        $this->assertSame(2, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertSame('Открываю на сервере', end($this->answered)['text']);
    }

    public function test_continue_on_server_without_a_session_changes_nothing(): void
    {
        $job = $this->closedQuestion('Питання');

        $this->press('onec_resume_'.$job->question_id, $job->batch->service_message_id);

        $this->assertSame(0, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertSame('Нет сессии разбора', end($this->answered)['text']);
    }

    public function test_someone_else_cannot_continue_on_the_server(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->answered = [];

        $this->press('onec_resume_'.$job->question_id, $answerId, 777);

        $this->assertSame(0, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertSame([], $this->answered);

        // Another owner, in a chat of their own, gets a bare answer.
        config()->set('onec_helper.owner_ids', [self::OWNER, 555]);

        $this->press('onec_resume_'.$job->question_id, $answerId, 555);

        $this->assertSame(0, OnecJob::where('kind', OnecJob::KIND_RESUME)->count());
        $this->assertCount(1, $this->answered);
        $this->assertArrayNotHasKey('text', $this->answered[0]);
    }

    public function test_the_worker_gets_a_resume_with_the_session_of_the_analysis_and_the_messages_of_the_question(): void
    {
        $this->sendText(100, 'Питання');
        $this->sendPhoto(101);
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $questionJob = OnecJob::sole();
        $this->claim()->assertOk();
        $this->postResult($questionJob, ['session_id' => 'analysis-session', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();
        $answerId = $this->nextBotMessageId - 1;

        // A follow-up analysed and one cancelled do not go into the resume.
        $this->sendPhoto(500, 'Уточнення', $this->replyTo($answerId));
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $followupJob = OnecJob::where('kind', OnecJob::KIND_FOLLOWUP)->sole();
        $this->claim()->assertOk();
        $this->postResult($followupJob, ['session_id' => 'analysis-session-2', 'for_user' => 'Ще розбір.'])->assertNoContent();
        $this->sendText(600, 'Не треба', $this->replyTo($answerId));
        $this->press('onec_cancel_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);

        $this->press('onec_resume_'.$questionJob->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();

        $response = $this->claim()->assertOk();

        $this->assertSame(['id' => $resume->id, 'kind' => 'resume', 'attempt' => 1], $response->json('job'));
        $this->assertSame('analysis-session-2', $response->json('question.session_id'));
        $this->assertSame(['Питання', null], array_column($response->json('messages'), 'text'));
        $this->assertSame(1, $response->json('messages.1.screen'));
        $this->assertNull($response->json('followup'));
    }

    public function test_the_result_of_a_resume_answers_the_message_of_the_button_and_keeps_the_session_of_the_analysis(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($resume, $this->serverSession(['folder' => null]))->assertNoContent();

        $resume->refresh();
        $this->assertSame(OnecJob::STATUS_DONE, $resume->status);
        $this->assertSame('380c2274-6f3a-4c1e-9d55-0b6a1f2e7c11', $resume->session_id);
        $this->assertNull($resume->folder);
        $this->assertSame('analysis-session', $job->question->fresh()->session_id);

        $this->assertCount(1, $this->sent);
        $this->assertStringStartsWith('Сессия Claude на сервере запущена, окно открыто.', $this->sent[0]['text']);
        $this->assertSame($answerId, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);

        // A reply to it is a follow-up, in the session of the analysis.
        $this->sendText(500, 'Уточнення', $this->replyTo($this->nextBotMessageId));
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);
        $response = $this->claim()->assertOk();
        $this->assertSame('followup', $response->json('job.kind'));
        $this->assertSame('analysis-session', $response->json('question.session_id'));
    }

    public function test_the_heartbeat_of_a_resume_is_kept_and_its_note_is_not_shown(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->claim()->assertOk();
        $this->edits = [];
        $this->travel(10)->seconds();

        $this->heartbeat($resume, ['note' => 'запускаю сессию'])->assertNoContent();

        $this->assertTrue($resume->fresh()->heartbeat_at->equalTo(now()->startOfSecond()));
        $this->assertSame([], $this->edits);
    }

    public function test_a_failed_window_comes_with_the_retry_button_and_is_retried(): void
    {
        $job = $this->windowQuestion();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postFail($job, 'claude: not logged in')->assertNoContent();

        $failure = $this->sent[0];
        $this->assertSame('Не удалось открыть на сервере: claude: not logged in', $failure['text']);
        $this->assertSame($job->batch->service_message_id, json_decode($failure['reply_parameters'], true)['message_id']);
        $this->assertSame('onec_retry_'.$job->id, json_decode($failure['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);
        $this->edits = [];

        $this->press('onec_retry_'.$job->id, $this->nextBotMessageId);

        $this->assertSame(OnecJob::STATUS_QUEUED, $job->fresh()->status);
        $this->assertSame('Принял: «'.self::TITLE.'». 1 сообщение, 0 скриншотов. Открываю на сервере.', $this->edits[0]['text']);
        $this->assertSame(['id' => $job->id, 'kind' => 'window', 'attempt' => 1], $this->claim()->assertOk()->json('job'));
    }

    public function test_a_failed_resume_answers_the_message_of_the_button_and_is_retried(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->claim()->assertOk();
        $this->sent = [];
        $this->edits = [];

        $this->postFail($resume, 'claude: session not found')->assertNoContent();

        $this->assertSame(OnecJob::STATUS_FAILED, $resume->fresh()->status);
        $failure = $this->sent[0];
        $this->assertSame('Не удалось открыть на сервере: claude: session not found', $failure['text']);
        $this->assertSame($answerId, json_decode($failure['reply_parameters'], true)['message_id']);
        $this->assertSame('onec_retry_'.$resume->id, json_decode($failure['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);

        // A failed resume is not in the way of a new one.
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $newer = OnecJob::where('kind', OnecJob::KIND_RESUME)->latest('id')->first();
        $this->assertNotSame($resume->id, $newer->id);
        $newer->update(['status' => OnecJob::STATUS_FAILED]);

        // The older failure is not the latest resume of the question; the newer is.
        $this->press('onec_retry_'.$resume->id, $this->nextBotMessageId);
        $this->assertSame(OnecJob::STATUS_FAILED, $resume->fresh()->status);
        $this->assertSame('Уже повторено', end($this->answered)['text']);

        $this->press('onec_retry_'.$newer->id, $this->nextBotMessageId);
        $this->assertSame(OnecJob::STATUS_QUEUED, $newer->fresh()->status);
        $this->assertSame('Повторяю', end($this->answered)['text']);
        $this->assertSame([], $this->edits);
    }

    public function test_the_resumes_of_another_question_do_not_stop_a_retry(): void
    {
        [$first, $firstAnswerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$first->question_id, $firstAnswerId);
        $firstResume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->claim()->assertOk();
        $this->postFail($firstResume, 'claude: limit')->assertNoContent();
        $failureId = $this->nextBotMessageId;

        [$second, $secondAnswerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$second->question_id, $secondAnswerId);

        $this->press('onec_retry_'.$firstResume->id, $failureId);

        $this->assertSame(OnecJob::STATUS_QUEUED, $firstResume->fresh()->status);
        $this->assertSame('Повторяю', end($this->answered)['text']);
    }

    public function test_the_command_retries_a_resume_in_reply_to_the_message_of_the_button(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();
        $this->claim()->assertOk();
        $this->postFail($resume, 'claude: limit')->assertNoContent();
        $this->sent = [];

        $this->artisan('onec-helper:retry', ['job' => $resume->id])->assertSuccessful();

        $this->assertSame(OnecJob::STATUS_QUEUED, $resume->fresh()->status);
        $this->assertSame('Открываю на сервере ещё раз.', $this->sent[0]['text']);
        $this->assertSame($answerId, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
    }

    public function test_the_supervisor_warns_about_a_window_and_a_resume_nobody_takes(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $window = $this->windowQuestion();
        $this->sent = [];

        $this->supervise(6);

        $this->assertSame([
            'Сервер 1С не забирает вопросы уже 5 минут: «'.self::TITLE.'» в очереди.',
            'Сервер 1С не забирает вопросы уже 5 минут: «'.self::TITLE.'» в очереди.',
        ], array_column($this->sent, 'text'));
        $this->assertSame(
            [$answerId, $window->batch->service_message_id],
            array_map(fn ($message) => json_decode($message['reply_parameters'], true)['message_id'], $this->sent)
        );

        $this->supervise(10);
        $this->assertCount(2, $this->sent);
    }

    public function test_the_supervisor_returns_a_silent_resume_to_the_queue_then_fails_it(): void
    {
        [$job, $answerId] = $this->analysedQuestion();
        $this->press('onec_resume_'.$job->question_id, $answerId);
        $resume = OnecJob::where('kind', OnecJob::KIND_RESUME)->sole();

        $this->assertSame(1, $this->claim()->assertOk()->json('job.attempt'));
        $this->supervise(16);
        $this->assertSame(OnecJob::STATUS_QUEUED, $resume->fresh()->status);
        $this->assertSame(2, $this->claim()->assertOk()->json('job.attempt'));
        $this->supervise(16);
        $this->assertSame(3, $this->claim()->assertOk()->json('job.attempt'));
        $this->sent = [];

        $this->supervise(16);

        $this->assertSame(OnecJob::STATUS_FAILED, $resume->fresh()->status);
        $this->assertSame('Не удалось открыть на сервере: сервер 1С не сообщал о работе 15 минут, попыток: 3', $this->sent[0]['text']);
        $this->assertSame($answerId, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertSame('onec_retry_'.$resume->id, json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);
    }

    public function test_the_supervisor_returns_a_silent_window_to_the_queue(): void
    {
        $window = $this->windowQuestion();
        $this->claim()->assertOk();

        $this->supervise(14);
        $this->assertSame(OnecJob::STATUS_PROCESSING, $window->fresh()->status);

        $this->supervise(2);
        $this->assertSame(OnecJob::STATUS_QUEUED, $window->fresh()->status);
        $this->assertSame(['id' => $window->id, 'kind' => 'window', 'attempt' => 2], $this->claim()->assertOk()->json('job'));
    }

    public function test_a_reply_queued_before_it_was_about_a_job_answers_the_service_message(): void
    {
        $job = $this->closedQuestion('Питання');
        $this->sent = [];
        $class = SendOnecReplies::class;
        // As a job of the previous code is stored in the queue: without jobId and parseMode.
        $queued = unserialize(sprintf(
            'O:%d:"%s":3:{s:7:"batchId";i:%d;s:5:"texts";%ss:8:"keyboard";a:0:{}}',
            strlen($class), $class, $job->batch_id, serialize(['Текст'])
        ));

        $this->assertNull($queued->jobId);
        $this->assertNull($queued->parseMode);
        $this->app->call([$queued, 'handle']);

        $this->assertSame(['Текст'], array_column($this->sent, 'text'));
        $this->assertSame($job->batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertArrayNotHasKey('parse_mode', $this->sent[0]);
    }

    /**
     * A question the worker analysed, its answer in two messages.
     *
     * @return array{0: OnecJob, 1: int} the job of the question and the id of the message "Разбор:"
     */
    private function analysedQuestion(): array
    {
        $job = $this->closedQuestion('Питання');
        $this->claim()->assertOk();
        $this->postResult($job, ['session_id' => 'analysis-session', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        return [$job->fresh(), $this->nextBotMessageId - 1];
    }

    /**
     * A question the owner opened on the server: the batch being gathered in
     * the chat, or a new one, closed by "Открыть на сервере".
     */
    private function windowQuestion(): OnecJob
    {
        static $messageId = 2000;

        if (! OnecBatch::where('status', OnecBatch::STATUS_COLLECTING)->exists()) {
            $this->sendText(++$messageId, 'Питання');
        }

        $batch = OnecBatch::where('status', OnecBatch::STATUS_COLLECTING)->latest('id')->firstOrFail();
        $this->press('onec_window_'.$batch->id, $batch->service_message_id);

        return OnecJob::where('batch_id', $batch->id)->sole();
    }

    /**
     * The result of a job "window" or "resume".
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function serverSession(array $fields = []): array
    {
        return array_merge([
            'session_id' => '380c2274-6f3a-4c1e-9d55-0b6a1f2e7c11',
            'attach_id' => '380c2274',
            'folder' => 'questions/2026-11-02-raskhozhdenie',
            'project_dir' => 'C:\agents\onec',
            'window' => true,
        ], $fields);
    }

    private function heartbeat(OnecJob $job, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.heartbeat', ['job' => $job->id]), $body);
    }
}
