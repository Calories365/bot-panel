<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\OnecBatch;
use App\Models\OnecJob;
use App\Services\AudioConversionService;
use App\Services\TelegramServices\OnecHelperHandlers\BotApiFactory;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\File;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;

/**
 * The 1C helper bot with Telegram mocked: the webhook goes through
 * TelegramHandler with a mock Api, the queued jobs get the same mock from
 * BotApiFactory. The queue is sync: a delayed job runs at once, so the timer
 * of a question finds it not idle and does nothing.
 */
abstract class OnecHelperTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SECRET = 'webhook-secret';

    protected const OWNER = 385493515;

    protected const TOKEN = 'worker-token-0123456789';

    protected Bot $bot;

    /** @var list<array<string, mixed>> */
    protected array $sent = [];

    /** @var list<array<string, mixed>> */
    protected array $edits = [];

    /** @var list<array<string, mixed>> */
    protected array $answered = [];

    /** @var list<array<string, mixed>> */
    protected array $markupEdits = [];

    protected int $nextBotMessageId = 9000;

    private int $nextUpdateId = 1;

    protected ?string $transcript = 'Голосом: перевірте, будь ласка, залишки';

    /** @var array<string, mixed>|null the JSON the model answers with; null for a failure */
    protected ?array $structure = [
        'title' => 'Расхождение остатков по счёту 361 у ТОВ «Альфа»',
        'summary' => 'Главбух спрашивает, почему остатки по счёту 361 не сходятся с актом сверки. Приложены скриншоты оборотно-сальдовой ведомости.',
        'entities' => [
            'documents' => ['РН-000123'],
            'dates' => ['31.10.2026'],
            'counterparties' => ['ТОВ «Альфа»'],
            'accounts' => ['361'],
            'amounts' => ['12 500,00'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('onec_helper.owner_ids', [self::OWNER]);
        config()->set('onec_helper.worker_token', self::TOKEN);
        config()->set('onec_helper.batch_seconds', 60);

        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'api.telegram.org/file/*' => Http::response('file-bytes', 200),
            '*/v1/chat/completions' => fn () => $this->structure === null
                ? Http::response(['error' => ['message' => 'Server error']], 500)
                : Http::response(['choices' => [['message' => ['content' => json_encode($this->structure, JSON_UNESCAPED_UNICODE)]]]], 200),
        ]);

        $this->partialMock(AudioConversionService::class, fn ($mock) => $mock->shouldReceive('processAudioMessage')
            ->andReturnUsing(fn () => $this->transcript));

        $this->bindTelegramApiMock();

        $this->bot = Bot::factory()->active()->create([
            'name' => 'YellowHelperAIBot',
            'type_id' => BotType::factory()->create(['name' => 'OnecHelper'])->id,
            'secret_token' => hash('sha256', self::SECRET),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $update  without update_id
     */
    protected function sendUpdate(array $update): void
    {
        $this->postJson(
            route('bot.webhook.handle', ['bot' => $this->bot->name]),
            ['update_id' => $this->nextUpdateId++] + $update,
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET]
        )->assertOk();
    }

    /**
     * @param  array<string, mixed>  $content  text, photo, document, voice…
     */
    protected function sendMessage(int $messageId, array $content, int $from = self::OWNER): void
    {
        $this->sendUpdate(['message' => [
            'message_id' => $messageId,
            'from' => ['id' => $from, 'is_bot' => false, 'first_name' => 'Максим', 'last_name' => 'Кубичка', 'language_code' => 'ru'],
            'chat' => ['id' => $from, 'first_name' => 'Максим', 'type' => 'private'],
            'date' => 1793532300,
        ] + $content]);
    }

    protected function sendText(int $messageId, string $text, array $extra = []): void
    {
        $this->sendMessage($messageId, ['text' => $text] + $extra);
    }

    protected function sendPhoto(int $messageId, ?string $caption = null, array $extra = []): void
    {
        $this->sendMessage($messageId, [
            'photo' => [
                ['file_id' => "small-{$messageId}.jpg", 'file_unique_id' => "s{$messageId}", 'width' => 90, 'height' => 51, 'file_size' => 1000],
                ['file_id' => "large-{$messageId}.jpg", 'file_unique_id' => "l{$messageId}", 'width' => 1280, 'height' => 720, 'file_size' => 90000],
                ['file_id' => "medium-{$messageId}.jpg", 'file_unique_id' => "m{$messageId}", 'width' => 320, 'height' => 180, 'file_size' => 9000],
            ],
        ] + ($caption === null ? [] : ['caption' => $caption]) + $extra);
    }

    protected function sendDocument(int $messageId, string $name, string $mime, array $extra = []): void
    {
        $this->sendMessage($messageId, [
            'document' => ['file_id' => "doc-{$messageId}-{$name}", 'file_unique_id' => "d{$messageId}", 'file_name' => $name, 'mime_type' => $mime, 'file_size' => 2000],
        ] + $extra);
    }

    protected function sendVoice(int $messageId): void
    {
        $this->sendMessage($messageId, [
            'voice' => ['file_id' => "voice-{$messageId}.oga", 'file_unique_id' => "v{$messageId}", 'duration' => 4, 'mime_type' => 'audio/ogg', 'file_size' => 9000],
        ]);
    }

    protected function press(string $data, int $messageId, int $from = self::OWNER): void
    {
        $this->sendUpdate(['callback_query' => [
            'id' => 'cb-'.$this->nextUpdateId,
            'from' => ['id' => $from, 'is_bot' => false, 'first_name' => 'Максим'],
            'message' => [
                'message_id' => $messageId,
                'from' => ['id' => 1, 'is_bot' => true, 'first_name' => 'YellowHelperAIBot'],
                'chat' => ['id' => $from, 'type' => 'private'],
                'date' => 1793532300,
                'text' => 'Собираю вопрос',
            ],
            'chat_instance' => '1',
            'data' => $data,
        ]]);
    }

    /**
     * A reply to a message the bot sent.
     *
     * @return array<string, mixed>
     */
    protected function replyTo(int $botMessageId): array
    {
        return ['reply_to_message' => [
            'message_id' => $botMessageId,
            'from' => ['id' => 1, 'is_bot' => true, 'first_name' => 'YellowHelperAIBot'],
            'chat' => ['id' => self::OWNER, 'type' => 'private'],
            'date' => 1793532300,
            'text' => 'Разбор',
        ]];
    }

    protected function latestBatch(): OnecBatch
    {
        return OnecBatch::latest('id')->firstOrFail();
    }

    /**
     * Close the batch as its timer does after the batch time without messages.
     */
    protected function closeByTimer(OnecBatch $batch): void
    {
        $this->travel(61)->seconds();
        \App\Jobs\CloseOnecBatch::dispatchSync($batch->id);
    }

    protected function claim(): TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.claim'));
    }

    protected function postResult(OnecJob|int $job, array $body): TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.result', ['job' => $job instanceof OnecJob ? $job->id : $job]), $body);
    }

    protected function postFail(OnecJob|int $job, string $error): TestResponse
    {
        return $this->withToken(self::TOKEN)->postJson(route('onec-helper.jobs.fail', ['job' => $job instanceof OnecJob ? $job->id : $job]), ['error' => $error]);
    }

    /**
     * A question closed by its button, with its job in the queue.
     */
    protected function closedQuestion(string $text): OnecJob
    {
        static $messageId = 1000;

        $this->sendText(++$messageId, $text);
        $batch = OnecBatch::where('status', OnecBatch::STATUS_COLLECTING)->latest('id')->firstOrFail();
        $this->press('onec_close_'.$batch->id, $batch->service_message_id);

        return OnecJob::where('batch_id', $batch->id)->sole();
    }

    protected function supervise(int $minutesLater): void
    {
        $this->travel($minutesLater)->minutes();
        $this->artisan('onec-helper:supervise')->assertSuccessful();
    }

    private function bindTelegramApiMock(): void
    {
        $api = Mockery::mock(Api::class);

        $api->shouldReceive('sendMessage')->andReturnUsing(function (array $params) {
            $this->sent[] = $params;

            return new Message([
                'message_id' => ++$this->nextBotMessageId,
                'chat' => ['id' => $params['chat_id'], 'type' => 'private'],
                'date' => time(),
                'text' => $params['text'],
            ]);
        });

        $api->shouldReceive('editMessageText')->andReturnUsing(function (array $params) {
            $this->edits[] = $params;

            return new Message([
                'message_id' => $params['message_id'],
                'chat' => ['id' => $params['chat_id'], 'type' => 'private'],
                'date' => time(),
                'text' => $params['text'],
            ]);
        });

        $api->shouldReceive('editMessageReplyMarkup')->andReturnUsing(function (array $params) {
            $this->markupEdits[] = $params;

            return new Message([
                'message_id' => $params['message_id'],
                'chat' => ['id' => $params['chat_id'], 'type' => 'private'],
                'date' => time(),
            ]);
        });

        $api->shouldReceive('answerCallbackQuery')->andReturnUsing(function (array $params) {
            $this->answered[] = $params;

            return true;
        });

        $api->shouldReceive('getFile')->andReturnUsing(fn (array $params) => new File([
            'file_id' => $params['file_id'],
            'file_path' => 'files/'.$params['file_id'],
        ]));

        $this->app->extend(TelegramHandler::class, function ($handler) use ($api) {
            $factory = new \ReflectionProperty($handler, 'apiFactory');
            $factory->setValue($handler, fn () => $api);

            return $handler;
        });

        $this->mock(BotApiFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($api));
    }
}
