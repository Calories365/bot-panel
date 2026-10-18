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
 * The meal draft in the calories bot: photos, voice messages and texts wait in
 * the diary until "Посчитать", one message shows the draft and what waits, its
 * buttons calculate, save the meal or drop it.
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

    /** @var array<int, array<string, mixed>> chat menu buttons set */
    private array $menuButtons = [];

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

        config()->set('services.diary_api.calculation_poll_seconds', 0);
        config()->set('services.diary_api.calculation_wait_seconds', 5);

        $this->bindTelegramApiMock();
    }

    public function test_a_photo_and_its_caption_wait_for_the_calculation_without_the_bot_token(): void
    {
        Http::fake(['api.telegram.org/file/botbot-token/photos/file_7.jpg' => Http::response('jpeg-bytes')]);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->once()
            ->with('photo', 'выпил стакан', ['contents' => 'jpeg-bytes', 'name' => 'file_7.jpg'], 555, 'ru')
            ->andReturn($this->waiting([['id' => 1, 'kind' => 'photo', 'text' => 'выпил стакан']])));

        $this->send(['photo' => [['file_id' => 'small', 'width' => 90, 'height' => 90], ['file_id' => 'large', 'width' => 1280, 'height' => 960]], 'caption' => 'выпил стакан']);

        $this->assertStringContainsString('• Фото: «выпил стакан»', $this->sent[0]['text']);
        $this->assertSame('draft_calculate', json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][0][0]['callback_data']);
    }

    public function test_a_text_waits_and_the_old_draft_message_is_replaced(): void
    {
        Cache::put('meal_draft_message_111111', 77);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->once()
            ->with('text', 'и ещё яблоко', null, 555, 'ru')
            ->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'и ещё яблоко']], $this->loadFixture('DiaryAPI/draft.json')['draft'])));

        $this->send(['text' => 'и ещё яблоко']);

        $this->assertSame([77], $this->deleted);
        $this->assertNotSame(77, Cache::get('meal_draft_message_111111'));
        $this->assertStringContainsString('<b>Добавлено, ещё не посчитано:</b>', $this->sent[0]['text']);
        $this->assertSame('Пересчитать с добавленным', json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][0][0]['text']);
    }

    public function test_calculate_waits_for_the_diary_and_shows_the_draft(): void
    {
        Cache::put('meal_draft_message_111111', 77);
        $done = $this->loadFixture('DiaryAPI/draft.json') + ['inputs' => [], 'calculation' => ['status' => 'done', 'understood' => true, 'not_found' => ['Чай']]];
        $this->diary(function ($mock) use ($done) {
            $mock->shouldReceive('draftCalculate')->once()->with(555, 'ru')->andReturn($this->waiting([], null, ['status' => 'running']));
            $mock->shouldReceive('draftShow')->twice()->with(555, 'ru')->andReturn($this->waiting([], null, ['status' => 'running']), $done);
        });

        $this->press('draft_calculate');

        $this->assertSame(__('calories365-bot.draft_calculating'), $this->answers[0]['text']);
        $draft = $this->sent[0]['text'];
        $this->assertStringContainsString('1. Творог 5% — 225 г, 306 ккал', $draft);
        $this->assertStringContainsString('2. Кефир &lt;Олимп&gt; 1% — 250 г, 98 ккал — по этикетке', $draft);
        $this->assertStringContainsString('<b>Итого: 404 ккал</b> · Б 47 · Ж 14 · У 17', $draft);
        $this->assertStringContainsString('Не нашёл: Чай', $draft);

        $keyboard = json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'];
        $saves = array_column($keyboard[0], 'callback_data');
        sort($saves);
        $this->assertSame(['draft_save_dinner', 'draft_save_morning', 'draft_save_supper'], $saves);
        $this->assertSame([77], $this->deleted);
    }

    public function test_the_draft_marks_fast_carbohydrates_and_tells_the_glycemic_load(): void
    {
        $done = $this->loadFixture('DiaryAPI/draft.json') + ['inputs' => [], 'calculation' => ['status' => 'done', 'understood' => true, 'not_found' => []]];
        $done['draft']['items'][0]['quality'] = ['fast_carbohydrates' => true, 'ultra_processed' => true];
        $done['draft']['total'] += ['sugars' => 23.4, 'glycemic_load' => 31.6, 'glycemic_load_level' => 'medium'];
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCalculate')->andReturn($done));

        $this->press('draft_calculate');

        $draft = $this->sent[0]['text'];
        $this->assertStringContainsString('1. Творог 5% — 225 г, 306 ккал — быстрые углеводы, ультрапереработанный', $draft);
        $this->assertStringContainsString('Сахар 23 г · гликемическая нагрузка 32, средняя', $draft);
    }

    public function test_a_weight_and_an_activity_the_diary_kept_are_told(): void
    {
        $this->diary(function ($mock) {
            $mock->shouldReceive('draftCollect')->once()->with('text', 'вешу 82,4', null, 555, 'ru')
                ->andReturn($this->waiting([]) + ['recorded' => ['weight' => 82.4, 'activities' => []]]);
            $mock->shouldReceive('draftCalculate')->andReturn($this->waiting([], null, [
                'status' => 'done',
                'understood' => true,
                'not_found' => [],
                'recorded' => ['weight' => null, 'activities' => [['id' => 1, 'title' => 'Бег 5 км', 'minutes' => 30, 'calories' => 365]]],
            ]));
        });

        $this->send(['text' => 'вешу 82,4']);
        $this->press('draft_calculate');

        $this->assertSame('Записал вес 82.4 кг.', $this->sent[0]['text']);
        $this->assertSame('Записал: Бег 5 км, 30 мин, ≈365 ккал.', $this->sent[1]['text']);
        $this->assertCount(2, $this->sent);
    }

    public function test_a_calculation_longer_than_the_bot_waits_asks_to_press_again(): void
    {
        config()->set('services.diary_api.calculation_wait_seconds', 0);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCalculate')->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'гречка']], null, ['status' => 'running'])));

        $this->press('draft_calculate');

        $this->assertSame(__('calories365-bot.draft_still_calculating'), $this->sent[0]['text']);
    }

    public function test_calculate_pressed_again_after_it_is_done_shows_the_draft(): void
    {
        $this->diary(function ($mock) {
            $mock->shouldReceive('draftCalculate')->andReturn(['error' => 'Unprocessable', 'status' => 422, 'message' => 'nothing_to_calculate']);
            $mock->shouldReceive('draftShow')->andReturn($this->loadFixture('DiaryAPI/draft.json') + ['inputs' => [], 'calculation' => null]);
        });

        $this->press('draft_calculate');

        $this->assertStringContainsString('1. Творог 5%', $this->sent[0]['text']);
    }

    public function test_too_many_inputs_for_one_calculation_are_refused(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->andReturn(['error' => 'Unprocessable', 'status' => 422, 'message' => 'too_many_inputs']));

        $this->send(['text' => 'и ещё чай']);

        $this->assertSame(__('calories365-bot.draft_too_many'), $this->sent[0]['text']);
    }

    public function test_the_edit_button_opens_the_draft_in_the_mini_app(): void
    {
        $this->bot->update(['web_app_url' => 'https://diary.test']);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'гречка']])));

        $this->send(['text' => 'гречка']);

        $actions = json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][1];
        $this->assertSame(['text' => 'Изменить', 'web_app' => ['url' => 'https://diary.test/voice']], $actions[0]);
        $this->assertSame('draft_cancel', $actions[1]['callback_data']);
    }

    public function test_the_how_to_use_button_sends_the_guide_with_the_app_button(): void
    {
        $this->bot->update(['web_app_url' => 'https://diary.test']);

        $this->send(['text' => 'Как пользоваться']);

        $this->assertStringContainsString('Как вести дневник в боте', $this->sent[0]['text']);
        $this->assertStringContainsString('«🥗 Открыть приложение»', $this->sent[0]['text']);
        $this->assertSame('https://diary.test', json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'][0][0]['web_app']['url']);
    }

    public function test_start_opens_the_app_from_the_menu_and_an_inline_button_never_from_the_keyboard(): void
    {
        $this->bot->update(['web_app_url' => 'https://diary.test']);

        $this->send(['text' => '/start']);

        $keyboards = array_map(fn (array $message) => json_decode((string) ($message['reply_markup'] ?? '{}'), true), $this->sent);
        $replyButtons = array_merge(...array_map(fn ($markup) => array_merge(...($markup['keyboard'] ?? [[]])), $keyboards));
        $this->assertNotEmpty($replyButtons);
        $this->assertSame([], array_filter($replyButtons, fn ($button) => isset($button['web_app'])));

        $inline = array_values(array_filter($keyboards, fn ($markup) => isset($markup['inline_keyboard'][0][0]['web_app'])));
        $this->assertSame('https://diary.test', $inline[0]['inline_keyboard'][0][0]['web_app']['url']);
        $this->assertSame('web_app', json_decode($this->menuButtons[0]['menu_button'], true)['type']);
    }

    public function test_only_the_calculation_counts_for_the_spam_guard(): void
    {
        config()->set('services.voice.max_per_hour', 1);
        $done = $this->loadFixture('DiaryAPI/draft.json') + ['inputs' => [], 'calculation' => ['status' => 'done', 'understood' => true, 'not_found' => []]];
        $this->diary(function ($mock) use ($done) {
            $mock->shouldReceive('draftInput')->twice()->andReturn($this->loadFixture('DiaryAPI/draft.json'));
            $mock->shouldReceive('draftCollect')->twice()->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'и ещё яблоко']]));
            $mock->shouldReceive('draftCalculate')->once()->andReturn($done);
        });

        $this->send(['text' => '2 250']);
        $this->send(['text' => 'убери 1']);
        $this->send(['text' => 'и ещё яблоко']);
        $this->send(['text' => 'и груша']);
        $this->press('draft_calculate');
        $this->press('draft_calculate');

        $this->assertStringContainsString('Слишком много сообщений подряд', end($this->sent)['text']);
    }

    public function test_a_calculation_without_food_gets_a_hint(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCalculate')->andReturn($this->waiting([], null, ['status' => 'done', 'understood' => false, 'not_found' => []])));

        $this->press('draft_calculate');

        $this->assertSame(__('calories365-bot.draft_not_understood'), $this->sent[0]['text']);
        $this->assertFalse(Cache::has('meal_draft_message_111111'));
    }

    public function test_a_failed_calculation_keeps_what_waits_and_asks_to_try_again(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCalculate')->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'гречка']], null, ['status' => 'failed'])));

        $this->press('draft_calculate');

        $this->assertSame(__('calories365-bot.draft_not_read'), $this->sent[0]['text']);
        $this->assertStringContainsString('• Текст: «гречка»', $this->sent[1]['text']);
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
     * An answer of the diary about the draft and what waits for its calculation.
     *
     * @param  array<int, array<string, mixed>>  $inputs
     * @param  array<string, mixed>|null  $draft
     * @param  array<string, mixed>|null  $calculation
     * @return array<string, mixed>
     */
    private function waiting(array $inputs, ?array $draft = null, ?array $calculation = null): array
    {
        return ['draft' => $draft, 'inputs' => $inputs, 'calculation' => $calculation, 'understood' => true, 'not_found' => []];
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
        $api->shouldReceive('post')->with('setChatMenuButton', Mockery::any())->andReturnUsing(function (string $method, array $p) {
            $this->menuButtons[] = $p;
        });
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
