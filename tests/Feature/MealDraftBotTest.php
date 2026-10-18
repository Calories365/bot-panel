<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\BotUser;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\File;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;
use Tests\TestFixtures;

/**
 * The meal draft in the calories bot: a photo or a text goes to the draft in the
 * diary, one message shows the draft, its buttons save the meal or drop it.
 */
class MealDraftBotTest extends TestCase
{
    use RefreshDatabase;
    use TestFixtures;

    private const SECRET = 'webhook-secret';

    private Bot $bot;

    /** @var array<int, array<string, mixed>> messages sent, in order */
    private array $sent = [];

    /** @var array<int, int> ids of the messages deleted */
    private array $deleted = [];

    /** @var array<int, array<string, mixed>> answers to button presses */
    private array $answers = [];

    private int $updateId = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ru');
        Http::preventStrayRequests();

        BotType::factory()->create(['id' => 6, 'name' => 'Calories']);
        $this->bot = Bot::factory()->active()->create([
            'name' => 'Calories365Test_bot',
            'token' => 'bot-token',
            'secret_token' => hash('sha256', self::SECRET),
        ]);
        BotUser::factory()->locale('ru')->create(['bot_id' => $this->bot->id, 'telegram_id' => 111111, 'calories_id' => 555]);

        $this->bindTelegramApiMock();
    }

    public function test_a_photo_and_its_caption_go_to_the_draft_without_the_bot_token(): void
    {
        Http::fake(['api.telegram.org/file/botbot-token/photos/file_7.jpg' => Http::response('jpeg-bytes')]);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftInput')->once()
            ->with('выпил стакан', ['contents' => 'jpeg-bytes', 'name' => 'file_7.jpg'], 555, 'ru')
            ->andReturn($this->loadFixture('DiaryAPI/draft.json')));

        $this->send(['photo' => [['file_id' => 'small', 'width' => 90, 'height' => 90], ['file_id' => 'large', 'width' => 1280, 'height' => 960]], 'caption' => 'выпил стакан']);

        $this->assertStringContainsString('Кефир &lt;Олимп&gt; 1%', $this->sent[0]['text']);
    }

    public function test_a_text_goes_to_the_draft_and_the_old_draft_message_is_replaced(): void
    {
        Cache::put('meal_draft_message_111111', 77);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftInput')->once()
            ->with('и ещё яблоко', null, 555, 'ru')
            ->andReturn($this->loadFixture('DiaryAPI/draft.json')));

        $this->send(['text' => 'и ещё яблоко']);

        $this->assertSame([77], $this->deleted);
        $this->assertNotSame(77, Cache::get('meal_draft_message_111111'));
    }

    public function test_a_short_edit_does_not_count_for_the_spam_guard(): void
    {
        config()->set('services.voice.max_per_hour', 1);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftInput')->times(3)->andReturn($this->loadFixture('DiaryAPI/draft.json')));

        $this->send(['text' => '2 250']);
        $this->send(['text' => 'убери 1']);
        $this->send(['text' => 'и ещё яблоко']);
        $this->send(['text' => 'и груша']);

        $this->assertStringContainsString('Слишком много сообщений подряд', end($this->sent)['text']);
    }

    public function test_a_message_without_food_gets_a_hint(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftInput')->andReturn(['draft' => null, 'understood' => false, 'not_found' => []]));

        $this->send(['text' => 'привет']);

        $this->assertSame(__('calories365-bot.draft_not_understood'), $this->sent[0]['text']);
        $this->assertFalse(Cache::has('meal_draft_message_111111'));
    }

    public function test_an_input_the_diary_could_not_read_asks_to_try_again(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftInput')->andReturn(['error' => 'Service Unavailable', 'status' => 503]));

        $this->send(['text' => 'гречка с котлетой']);

        $this->assertSame(__('calories365-bot.draft_not_read'), $this->sent[0]['text']);
    }

    public function test_a_save_button_saves_the_meal_and_removes_the_draft_message(): void
    {
        Cache::put('meal_draft_message_111111', 77);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftSave')->once()
            ->with('dinner', date('Y-m-d'), 555, 'ru')
            ->andReturn(['total' => ['calories' => 403.5, 'proteins' => 46.8, 'fats' => 13.8, 'carbohydrates' => 17.1, 'fibers' => 0]]));

        $this->press('draft_save_dinner');

        $this->assertSame([77], $this->deleted);
        $this->assertStringContainsString(__('calories365-bot.data_saved_you_consumed'), $this->sent[0]['text']);
        $this->assertStringContainsString('404', $this->sent[0]['text']);
        $this->assertFalse(Cache::has('meal_draft_message_111111'));
        $this->assertCount(1, $this->answers);
    }

    public function test_saving_a_draft_that_is_gone_says_so(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftSave')->andReturn(['error' => 'Not Found', 'status' => 404]));

        $this->press('draft_save_morning');

        $this->assertSame(__('calories365-bot.draft_gone'), $this->sent[0]['text']);
    }

    public function test_the_cancel_button_drops_the_draft(): void
    {
        Cache::put('meal_draft_message_111111', 77);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCancel')->once()->with(555, 'ru')->andReturn(['draft' => null]));

        $this->press('draft_cancel');

        $this->assertSame([77], $this->deleted);
        $this->assertSame(__('calories365-bot.draft_cancelled'), $this->sent[0]['text']);
    }

    public function test_a_button_of_the_old_per_product_messages_says_it_is_out_of_date(): void
    {
        $this->press('save_morning');

        $this->assertSame([], $this->sent);
        $this->assertSame(__('calories365-bot.outdated_message'), $this->answers[0]['text']);
    }

    private function diary(callable $expectations): void
    {
        $this->partialMock(DiaryApiService::class, $expectations);
    }

    /**
     * @param  array<string, mixed>  $content  the message fields beyond the sender and the chat
     */
    private function send(array $content): void
    {
        $this->webhook(['message' => [
            'message_id' => 100,
            'from' => ['id' => 111111, 'is_bot' => false, 'first_name' => 'Максим', 'language_code' => 'ru'],
            'chat' => ['id' => 111111, 'first_name' => 'Максим', 'type' => 'private'],
            'date' => 1700000000,
        ] + $content]);
    }

    private function press(string $data): void
    {
        $this->webhook(['callback_query' => [
            'id' => 'callback-1',
            'from' => ['id' => 111111, 'is_bot' => false, 'first_name' => 'Максим', 'language_code' => 'ru'],
            'message' => [
                'message_id' => 77,
                'chat' => ['id' => 111111, 'first_name' => 'Максим', 'type' => 'private'],
                'date' => 1700000000,
                'text' => 'draft',
            ],
            'chat_instance' => '1',
            'data' => $data,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function webhook(array $update): void
    {
        $this->postJson(route('bot.webhook.handle', ['bot' => $this->bot->name]), ['update_id' => ++$this->updateId] + $update, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();
    }

    private function bindTelegramApiMock(): void
    {
        $api = Mockery::mock(Api::class);

        $api->shouldReceive('getFile')->andReturn(new File(['file_path' => 'photos/file_7.jpg']));
        $api->shouldReceive('sendChatAction');
        $api->shouldReceive('deleteMessage')->andReturnUsing(function (array $p) {
            $this->deleted[] = $p['message_id'];

            return true;
        });
        $api->shouldReceive('answerCallbackQuery')->andReturnUsing(function (array $p) {
            $this->answers[] = $p;

            return true;
        });
        $api->shouldReceive('sendMessage')->andReturnUsing(function (array $p) {
            $this->sent[] = $p;

            return new Message([
                'message_id' => random_int(1000, 9000),
                'chat' => ['id' => $p['chat_id'], 'type' => 'private'],
                'date' => time(),
                'text' => 'stub',
            ]);
        });

        $this->app->extend(TelegramHandler::class, function ($handler) use ($api) {
            $property = new \ReflectionProperty($handler, 'apiFactory');
            $property->setValue($handler, fn () => $api);

            return $handler;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
