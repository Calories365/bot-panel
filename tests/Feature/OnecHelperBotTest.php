<?php

namespace Tests\Feature;

use App\Jobs\CloseOnecBatch;
use App\Jobs\RefreshOnecServiceMessage;
use App\Models\OnecBatch;
use App\Models\OnecBotMessage;
use App\Models\OnecJob;
use App\Models\OnecMessage;
use App\Models\OnecQuestion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The 1C helper bot gathers the messages of its owner into a question and
 * puts it in the queue of the worker on the 1C server.
 */
class OnecHelperBotTest extends OnecHelperTestCase
{
    public function test_the_first_message_opens_a_question_with_a_service_message_and_buttons(): void
    {
        $this->sendText(100, 'Добрий день! Чому не сходиться сальдо по 361?');

        $batch = $this->latestBatch();
        $this->assertSame(OnecBatch::STATUS_COLLECTING, $batch->status);
        $this->assertCount(1, $this->sent);
        $this->assertSame(self::OWNER, $this->sent[0]['chat_id']);
        $this->assertSame('Собираю вопрос: 1 сообщение…', $this->sent[0]['text']);
        $this->assertSame(100, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertSame([[
            ['text' => 'Разобрать сейчас', 'callback_data' => 'onec_close_'.$batch->id],
            ['text' => 'Отменить', 'callback_data' => 'onec_cancel_'.$batch->id],
        ]], json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard']);
        $this->assertSame($this->nextBotMessageId, $batch->service_message_id);
    }

    public function test_messages_go_into_one_question_in_the_order_of_telegram(): void
    {
        // The queue handles them out of order.
        $this->sendText(105, 'Подивіться, будь ласка');
        $this->sendDocument(104, 'Звіт.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->sendPhoto(103, 'Ось скрін');
        $this->sendVoice(101);

        $this->assertSame(1, OnecQuestion::count());
        $this->assertCount(1, $this->sent);
        $this->assertSame(
            [101, 103, 104, 105],
            $this->latestBatch()->messages->pluck('telegram_message_id')->all()
        );
        $this->assertSame('Собираю вопрос: 4 сообщения, 1 скриншот…', end($this->edits)['text']);

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $messages = $this->claim()->assertOk()->json('messages');

        $this->assertSame([1, 2, 3, 4], array_column($messages, 'position'));
        $this->assertSame(['voice', 'photo', 'document', 'text'], array_column($messages, 'type'));
        $this->assertSame('Голосом: перевірте, будь ласка, залишки', $messages[0]['text']);
        $this->assertArrayNotHasKey('file', $messages[0]);
        $this->assertSame('Ось скрін', $messages[1]['text']);
        $this->assertSame('Звіт.xlsx', $messages[2]['file']['name']);
        $this->assertNull($messages[2]['screen']);
    }

    public function test_photo_keeps_its_largest_size_on_the_private_disk(): void
    {
        $this->sendPhoto(100);

        $message = OnecMessage::firstOrFail();
        $this->assertTrue($message->is_screen);
        $this->assertSame('image/jpeg', $message->file_mime);
        Storage::disk('local')->assertExists($message->file_path);
        $this->assertStringStartsWith('onec-helper/', $message->file_path);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'files/large-100.jpg'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'small-100') || str_contains($request->url(), 'medium-100'));
    }

    public function test_screenshots_are_numbered_in_the_order_of_telegram(): void
    {
        $this->sendPhoto(204);
        $this->sendDocument(203, 'Акт.pdf', 'application/pdf');
        $this->sendDocument(202, 'IMG_0001.PNG', 'image/png');
        $this->sendPhoto(201);

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $messages = $this->claim()->assertOk()->json('messages');

        $this->assertSame([1, 2, null, 3], array_column($messages, 'screen'));
        $this->assertSame('screen-1.jpg', $messages[0]['file']['name']);
        $this->assertSame('IMG_0001.PNG', $messages[1]['file']['name']);
        $this->assertSame('image/png', $messages[1]['file']['mime']);
        $this->assertSame('screen-3.jpg', $messages[3]['file']['name']);
        // Screenshots without a word: nothing to give the model.
        $this->assertSame('Принял: «Вопрос без текста». 4 сообщения, 3 скриншота. В очереди на разбор.', end($this->edits)['text']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/chat/completions'));
    }

    public function test_every_item_of_an_album_is_a_line_of_its_own(): void
    {
        $this->sendPhoto(301, 'Три скріни з ОСВ', ['media_group_id' => '13770']);
        $this->sendPhoto(303, null, ['media_group_id' => '13770']);
        $this->sendPhoto(302, null, ['media_group_id' => '13770']);

        $messages = $this->latestBatch()->messages;
        $this->assertCount(3, $messages);
        $this->assertSame(['13770', '13770', '13770'], $messages->pluck('media_group_id')->all());

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $claimed = $this->claim()->assertOk()->json('messages');

        $this->assertSame([1, 2, 3], array_column($claimed, 'screen'));
        $this->assertSame(['Три скріни з ОСВ', null, null], array_column($claimed, 'text'));
    }

    public function test_a_forwarded_message_keeps_its_author_and_time(): void
    {
        $this->sendText(401, 'Перевірте РН-000123', ['forward_origin' => [
            'type' => 'user',
            'sender_user' => ['id' => 5, 'is_bot' => false, 'first_name' => 'Анна', 'last_name' => 'Домашовець'],
            'date' => 1793530800,
        ]]);
        $this->sendText(402, 'Новина', ['forward_origin' => [
            'type' => 'channel',
            'chat' => ['id' => -100, 'type' => 'channel', 'title' => 'Евкаліпт Бухгалтерія'],
            'message_id' => 7,
            'date' => 1793530900,
        ]]);
        $this->sendText(403, 'Приховане', ['forward_origin' => [
            'type' => 'hidden_user',
            'sender_user_name' => 'Олена Б.',
            'date' => 1793531000,
        ]]);
        $this->sendText(404, 'Моє');

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $messages = $this->claim()->assertOk()->json('messages');

        $this->assertSame(['Анна Домашовець', 'Евкаліпт Бухгалтерія', 'Олена Б.', 'Максим Кубичка'], array_column($messages, 'author'));
        $this->assertSame([true, true, true, false], array_column($messages, 'forwarded'));
        $this->assertSame(Carbon::createFromTimestamp(1793530800, 'Europe/Kyiv')->toIso8601String(), $messages[0]['sent_at']);
        $this->assertSame(Carbon::createFromTimestamp(1793532300, 'Europe/Kyiv')->toIso8601String(), $messages[3]['sent_at']);
    }

    public function test_the_question_closes_after_the_batch_time_without_messages(): void
    {
        $this->sendText(100, 'Перше');
        $batch = $this->latestBatch();

        $this->travel(30)->seconds();
        $this->sendText(101, 'Друге');

        // The timer of the first message finds the second one.
        $this->travel(31)->seconds();
        \App\Jobs\CloseOnecBatch::dispatchSync($batch->id);
        $this->assertSame(OnecBatch::STATUS_COLLECTING, $batch->fresh()->status);
        $this->assertSame(0, OnecJob::count());

        $this->travel(30)->seconds();
        \App\Jobs\CloseOnecBatch::dispatchSync($batch->id);

        $this->assertSame(OnecBatch::STATUS_CLOSED, $batch->fresh()->status);
        $job = OnecJob::sole();
        $this->assertSame(OnecJob::KIND_QUESTION, $job->kind);
        $this->assertSame(OnecJob::STATUS_QUEUED, $job->status);
        $this->assertSame('Принял: «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 2 сообщения, 0 скриншотов. В очереди на разбор.', end($this->edits)['text']);
        $this->assertArrayNotHasKey('reply_markup', end($this->edits));

        // The next message opens a new question.
        $this->sendText(102, 'Інше питання');
        $this->assertSame(2, OnecQuestion::count());
    }

    public function test_the_button_closes_the_question_at_once(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();

        $this->press('onec_close_'.$batch->id, $batch->service_message_id);

        $this->assertSame(OnecBatch::STATUS_CLOSED, $batch->fresh()->status);
        $this->assertSame(1, OnecJob::where('status', OnecJob::STATUS_QUEUED)->count());
        $this->assertCount(1, $this->answered);

        // A second press does nothing.
        $this->press('onec_close_'.$batch->id, $batch->service_message_id);
        $this->assertSame(1, OnecJob::count());
    }

    public function test_cancel_drops_the_question_and_deletes_its_files(): void
    {
        $this->sendPhoto(100);
        $this->sendText(101, 'Не треба');
        $batch = $this->latestBatch();
        $path = OnecMessage::where('type', 'photo')->value('file_path');
        Storage::disk('local')->assertExists($path);

        $this->press('onec_cancel_'.$batch->id, $batch->service_message_id);

        $this->assertSame(OnecBatch::STATUS_CANCELLED, $batch->fresh()->status);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, OnecJob::count());
        $this->assertSame('Вопрос отменён.', end($this->edits)['text']);
        $this->claim()->assertNoContent();
    }

    public function test_the_service_message_is_edited_at_most_every_3_seconds_and_the_timer_waits_the_batch_time(): void
    {
        Bus::fake([RefreshOnecServiceMessage::class, CloseOnecBatch::class]);
        $this->freezeTime();

        $this->sendText(100, 'Перше');
        $this->sendText(101, 'Друге');

        // Too soon after the service message: the counters wait for the job.
        $this->assertSame([], $this->edits);
        Bus::assertDispatchedTimes(RefreshOnecServiceMessage::class, 1);
        Bus::assertDispatched(RefreshOnecServiceMessage::class, fn ($job) => $job->delay->equalTo(now()->addSeconds(3)));

        $this->travel(4)->seconds();
        $this->sendText(102, 'Третє');

        $this->assertCount(1, $this->edits);
        $this->assertSame('Собираю вопрос: 3 сообщения…', $this->edits[0]['text']);
        $this->assertCount(2, json_decode($this->edits[0]['reply_markup'], true)['inline_keyboard'][0]);
        Bus::assertDispatched(CloseOnecBatch::class, fn ($job) => $job->delay->equalTo(now()->addSeconds(60)));
    }

    public function test_a_stored_file_and_its_directory_can_be_read_by_another_user(): void
    {
        // On the server the queue worker writes as root, php-fpm reads as www-data.
        $root = sys_get_temp_dir().'/onec-helper-test-'.uniqid();
        mkdir($root, 0755);
        config()->set('filesystems.disks.local.root', $root);
        Storage::forgetDisk('local');

        $this->sendPhoto(100);

        $path = $root.'/'.OnecMessage::sole()->file_path;
        $this->assertSame('755', substr(sprintf('%o', fileperms(dirname($path))), -3));
        $this->assertSame('644', substr(sprintf('%o', fileperms($path)), -3));

        File::deleteDirectory($root);
    }

    public function test_a_file_the_disk_refuses_is_reported_and_not_added(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->sendPhoto(100);

        $this->assertSame(0, OnecMessage::count());
        $this->assertSame('Не удалось скачать файл из Telegram. Пришлите его ещё раз.', $this->sent[0]['text']);
    }

    public function test_someone_else_gets_nothing(): void
    {
        $this->sendMessage(100, ['text' => 'Привіт'], 777);
        $this->sendMessage(101, ['text' => '/start'], 777);

        $this->assertSame([], $this->sent);
        $this->assertSame(0, OnecQuestion::count());
    }

    public function test_someone_else_cannot_press_the_buttons(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();

        $this->press('onec_cancel_'.$batch->id, $batch->service_message_id, 777);

        $this->assertSame(OnecBatch::STATUS_COLLECTING, $batch->fresh()->status);
        $this->assertSame([], $this->answered);
    }

    public function test_a_command_does_not_go_into_a_question(): void
    {
        $this->sendText(100, '/start');
        $this->sendText(101, '/help');

        $this->assertSame(0, OnecQuestion::count());
        $this->assertCount(2, $this->sent);
        $this->assertStringStartsWith('Пересылайте сюда вопросы по базе 1С', $this->sent[0]['text']);
    }

    public function test_the_model_gives_the_question_its_title_summary_and_entities(): void
    {
        $this->sendText(100, 'Добрий день! Чому не сходиться сальдо по 361 з ТОВ «Альфа»?');
        $this->sendPhoto(101);

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);

        $question = OnecQuestion::sole();
        $this->assertSame('Расхождение остатков по счёту 361 у ТОВ «Альфа»', $question->title);
        $this->assertStringStartsWith('Главбух спрашивает', $question->summary);
        $this->assertSame(['361'], $question->entities['accounts']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v1/chat/completions')) {
                return false;
            }

            return $request['model'] === 'gpt-6-luna'
                && $request['response_format']['json_schema']['strict'] === true
                && str_contains($request['messages'][1]['content'], '1. Максим Кубичка: Добрий день! Чому не сходиться сальдо по 361')
                && str_contains($request['messages'][1]['content'], '2. Максим Кубичка: [скриншот 1]');
        });

        $claimed = $this->claim()->assertOk()->json('question');
        $this->assertSame(['РН-000123'], $claimed['entities']['documents']);
    }

    public function test_without_the_model_the_title_is_the_first_words_of_the_first_text(): void
    {
        $this->structure = null;

        $this->sendPhoto(100);
        $this->sendText(101, 'Добрий день, Максиме! Подивіться, будь ласка, чому в оборотно-сальдовій відомості по рахунку 361 не той залишок');

        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);

        $question = OnecQuestion::sole();
        $this->assertSame('Добрий день, Максиме! Подивіться, будь ласка, чому в оборотно-сальдовій', $question->title);
        $this->assertLessThanOrEqual(80, mb_strlen($question->title));
        $this->assertSame('', $question->summary);
        $this->assertSame(1, OnecJob::where('status', OnecJob::STATUS_QUEUED)->count());

        $claimed = $this->claim()->assertOk()->json('question');
        $this->assertSame('', $claimed['summary']);
        $this->assertSame([], $claimed['entities']['documents']);
    }

    public function test_an_answer_the_model_gives_in_another_shape_is_not_taken(): void
    {
        $this->structure = ['title' => '', 'summary' => 'x', 'entities' => []];

        $this->sendText(100, 'Чому не проводиться РН-000123?');
        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);

        $this->assertSame('Чому не проводиться РН-000123?', OnecQuestion::sole()->title);
    }

    public function test_a_voice_message_that_cannot_be_heard_is_not_added(): void
    {
        $this->transcript = null;

        $this->sendVoice(100);

        $this->assertSame(0, OnecMessage::count());
        $this->assertSame('Не удалось распознать голосовое. Пришлите его ещё раз или текстом.', $this->sent[0]['text']);
    }

    public function test_the_answer_comes_as_two_replies_to_the_service_message(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();
        $this->press('onec_close_'.$batch->id, $batch->service_message_id);
        $job = OnecJob::sole();
        $this->claim()->assertOk();
        $this->sent = [];

        $forBoss = "Анно, добрий день!\n\nСальдо *не* сходиться через РН-000123 <від 31.10>: її проведено двічі_ [див. звіт].";
        $this->postResult($job, [
            'session_id' => 'session-1',
            'for_user' => 'Документ РН-000123 проведён дважды.',
            'for_boss' => $forBoss,
        ])->assertNoContent();

        $this->assertCount(2, $this->sent);
        $this->assertSame("Разбор:\nДокумент РН-000123 проведён дважды.", $this->sent[0]['text']);
        $this->assertSame($forBoss, $this->sent[1]['text']);
        foreach ($this->sent as $message) {
            $this->assertSame($batch->service_message_id, json_decode($message['reply_parameters'], true)['message_id']);
            $this->assertArrayNotHasKey('parse_mode', $message);
        }
        $this->assertSame(2, OnecBotMessage::where('batch_id', $batch->id)->where('message_id', '>', $batch->service_message_id)->count());
    }

    /**
     * @return array<string, array{0: array<string, string|null>}>
     */
    public static function emptyTextsForTheBoss(): array
    {
        return [
            'an empty string' => [['for_boss' => '']],
            'absent' => [[]],
            'null' => [['for_boss' => null]],
            'only spaces' => [['for_boss' => "  \n "]],
        ];
    }

    #[DataProvider('emptyTextsForTheBoss')]
    public function test_without_a_text_for_the_boss_only_the_answer_is_sent(array $forBoss): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();
        $this->press('onec_close_'.$batch->id, $batch->service_message_id);
        $job = OnecJob::sole();
        $this->claim()->assertOk();
        $this->sent = [];

        $this->postResult($job, ['session_id' => 'session-1', 'for_user' => 'Ответ одним текстом.'] + $forBoss)->assertNoContent();

        $this->assertSame(["Разбор:\nОтвет одним текстом."], array_column($this->sent, 'text'));
        $this->assertSame($batch->service_message_id, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertSame(OnecJob::STATUS_DONE, $job->fresh()->status);
        $this->assertSame('session-1', $job->question->fresh()->session_id);

        // The bot remembers the one message, and a reply to it opens a follow-up.
        $this->sendText(500, 'Уточнення', $this->replyTo($this->nextBotMessageId));
        $this->assertSame(OnecBatch::KIND_FOLLOWUP, $this->latestBatch()->kind);
    }

    public function test_a_long_answer_is_split_between_paragraphs(): void
    {
        $this->sendText(100, 'Питання');
        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $job = OnecJob::sole();
        $this->claim()->assertOk();
        $this->sent = [];

        $paragraphs = [];
        foreach (range(1, 6) as $number) {
            $paragraphs[] = "Абзац {$number}. ".str_repeat('слово ', 250);
        }
        $forUser = implode("\n\n", $paragraphs);

        $this->postResult($job, ['session_id' => 's', 'for_user' => $forUser, 'for_boss' => 'Коротко.'])->assertNoContent();

        $answer = array_slice(array_column($this->sent, 'text'), 0, -1);
        $this->assertGreaterThan(1, count($answer));
        foreach ($answer as $index => $part) {
            $this->assertLessThanOrEqual(4096, mb_strlen($part));
            $this->assertStringStartsWith($index === 0 ? 'Разбор:' : 'Абзац', $part);
        }
        $this->assertSame("Разбор:\n".$forUser, implode("\n\n", $answer));
        $this->assertSame('Коротко.', end($this->sent)['text']);
    }

    public function test_a_reply_to_the_answer_opens_a_followup_that_gathers_the_next_messages(): void
    {
        [$question, $answerMessageId] = $this->answeredQuestion();

        $this->sendText(500, 'А якщо перепровести?', $this->replyTo($answerMessageId));
        $followup = $this->latestBatch();

        $this->assertSame(OnecBatch::KIND_FOLLOWUP, $followup->kind);
        $this->assertSame($question->id, $followup->question_id);
        $this->assertSame('Собираю уточнение: 1 сообщение…', end($this->sent)['text']);
        $this->assertSame(500, json_decode(end($this->sent)['reply_parameters'], true)['message_id']);

        // The next messages without a reply go to the follow-up.
        $this->sendPhoto(501);
        $this->assertSame([500, 501], $followup->messages()->pluck('telegram_message_id')->all());
        $this->assertSame(1, OnecQuestion::count());

        $this->closeByTimer($followup);

        $job = OnecJob::where('kind', OnecJob::KIND_FOLLOWUP)->sole();
        $this->assertSame($followup->id, $job->batch_id);
        $this->assertSame(
            'Принял уточнение к «Расхождение остатков по счёту 361 у ТОВ «Альфа»». 2 сообщения, 1 скриншот. В очереди на разбор.',
            end($this->edits)['text']
        );
    }

    public function test_a_reply_to_the_text_for_the_boss_and_to_the_service_message_opens_a_followup_too(): void
    {
        [$question] = $this->answeredQuestion();
        $serviceMessageId = $question->questionBatch()->service_message_id;

        $this->sendText(500, 'Уточнення', $this->replyTo($this->nextBotMessageId));
        $this->assertSame(OnecBatch::KIND_FOLLOWUP, $this->latestBatch()->kind);
        $this->press('onec_close_'.$this->latestBatch()->id, $this->latestBatch()->service_message_id);

        $this->sendText(501, 'Ще уточнення', $this->replyTo($serviceMessageId));
        $this->assertSame(2, OnecBatch::where('kind', OnecBatch::KIND_FOLLOWUP)->count());
        $this->assertSame(1, OnecQuestion::count());
    }

    public function test_a_reply_while_the_model_titles_the_question_opens_a_followup(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();
        $batch->update(['status' => OnecBatch::STATUS_CLOSED, 'closed_at' => now()]);

        $this->sendText(101, 'Уточнення', $this->replyTo($batch->service_message_id));

        $this->assertSame(OnecBatch::KIND_FOLLOWUP, $this->latestBatch()->kind);
    }

    public function test_a_reply_to_a_cancelled_question_opens_nothing(): void
    {
        $this->sendText(100, 'Питання');
        $batch = $this->latestBatch();
        $this->press('onec_cancel_'.$batch->id, $batch->service_message_id);

        $this->sendText(101, 'Уточнення', $this->replyTo($batch->service_message_id));

        $this->assertSame(1, OnecBatch::count());
        $this->assertSame('К этому вопросу уточнение не добавить: он отменён или не разобран. Пришлите вопрос заново.', end($this->sent)['text']);
    }

    /**
     * A question the worker answered: the answer and the text for the boss
     * are the last two messages of the bot.
     *
     * @return array{0: OnecQuestion, 1: int} the question and the id of the answer message
     */
    private function answeredQuestion(): array
    {
        $this->sendText(100, 'Питання');
        $this->press('onec_close_'.$this->latestBatch()->id, $this->nextBotMessageId);
        $job = OnecJob::sole();
        $this->claim()->assertOk();
        $this->postResult($job, ['session_id' => 'session-1', 'for_user' => 'Разбор.', 'for_boss' => 'Для Анни.'])->assertNoContent();

        return [OnecQuestion::sole(), $this->nextBotMessageId - 1];
    }
}
