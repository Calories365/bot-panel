<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\BotUser;
use App\Services\AudioConversionService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * A PNG of one pixel: the diary draws the chart, the bot only sends it.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    private Bot $bot;

    /** @var array<int, array<string, mixed>> messages sent, in order */
    private array $sent = [];

    /** @var array<int, int> ids of the messages deleted */
    private array $deleted = [];

    /** @var array<int, array<string, mixed>> answers to button presses */
    private array $answers = [];

    /** @var array<int, array<string, mixed>> photos sent, in order */
    private array $photos = [];

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

    public function test_a_photo_sent_as_a_file_waits_like_a_photo(): void
    {
        Http::fake(['api.telegram.org/file/botbot-token/photos/file_7.jpg' => Http::response('jpeg-bytes')]);
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->once()
            ->with('photo', 'этикетка', ['contents' => 'jpeg-bytes', 'name' => 'file_7.jpg'], 555, 'ru')
            ->andReturn($this->waiting([['id' => 1, 'kind' => 'photo', 'text' => 'этикетка']])));

        $this->send(['document' => ['file_id' => 'file', 'file_name' => 'IMG_1.jpg', 'mime_type' => 'image/jpeg'], 'caption' => 'этикетка']);

        $this->assertStringContainsString('• Фото: «этикетка»', $this->sent[0]['text']);
    }

    public function test_a_photo_over_the_limit_of_the_diary_is_made_smaller(): void
    {
        $big = tempnam(sys_get_temp_dir(), 'big-photo-').'.jpg';
        Process::run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=3000x3000,noise=alls=60:allf=t', '-frames:v', '1', '-q:v', '2', $big])->throw();
        $this->assertGreaterThan(1024 * 1024, filesize($big));

        Http::fake(['api.telegram.org/file/botbot-token/photos/file_7.jpg' => Http::response((string) file_get_contents($big))]);
        unlink($big);

        $sent = null;
        $this->diary(function ($mock) use (&$sent) {
            $mock->shouldReceive('draftCollect')->once()
                ->andReturnUsing(function ($kind, $text, $photo) use (&$sent) {
                    $sent = $photo;

                    return $this->waiting([['id' => 1, 'kind' => 'photo', 'text' => null]]);
                });
        });

        $this->send(['photo' => [['file_id' => 'large', 'width' => 3000, 'height' => 3000]]]);

        $this->assertSame('file_7.jpg', $sent['name']);
        $this->assertLessThanOrEqual(900 * 1024, strlen($sent['contents']));
        $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer($sent['contents']));
        $this->assertSame(1600, getimagesizefromstring($sent['contents'])[0]);
    }

    public function test_an_audio_file_waits_like_a_voice_message(): void
    {
        $this->partialMock(AudioConversionService::class, fn ($mock) => $mock->shouldReceive('processAudioMessage')->once()->andReturn('гречка 200 грамм'));
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->once()
            ->with('voice', 'гречка 200 грамм', null, 555, 'ru')
            ->andReturn($this->waiting([['id' => 1, 'kind' => 'voice', 'text' => 'гречка 200 грамм']])));

        $this->send(['audio' => ['file_id' => 'file', 'duration' => 3, 'mime_type' => 'audio/mpeg']]);

        $this->assertStringContainsString('• Голосовое: «гречка 200 грамм»', $this->sent[0]['text']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function messagesTheBotCannotRead(): array
    {
        return [
            'a sticker' => [['sticker' => ['file_id' => 's', 'width' => 512, 'height' => 512]]],
            'a round video' => [['video_note' => ['file_id' => 'v', 'length' => 240, 'duration' => 3]]],
            'a pdf file' => [['document' => ['file_id' => 'd', 'mime_type' => 'application/pdf']]],
            'an unknown command' => [['text' => '/unknown']],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    #[DataProvider('messagesTheBotCannotRead')]
    public function test_what_the_bot_cannot_read_gets_an_answer(array $content): void
    {
        $this->send($content);

        $this->assertSame(__('calories365-bot.draft_unsupported'), $this->sent[0]['text']);
    }

    public function test_the_statistics_menu_offers_the_insulin_chart_of_today(): void
    {
        $this->send(['text' => 'Статистика']);

        $rows = json_decode((string) json_encode($this->sent[0]['reply_markup']), true)['inline_keyboard'];
        $this->assertSame('insulin_day_'.date('Y-m-d'), end($rows)[0]['callback_data']);
    }

    public function test_the_insulin_button_sends_the_chart_with_what_lifted_and_lowered_it(): void
    {
        $this->bot->update(['web_app_url' => 'https://app.example/']);
        Cache::forget('bot:'.$this->bot->name);
        $this->diary(fn ($mock) => $mock->shouldReceive('insulinDay')->once()->with(date('Y-m-d'), 555, 'ru')->andReturn($this->insulinDay()));

        $this->send(['text' => '📈 Инсулин']);

        $this->assertSame([], $this->sent);
        $photo = $this->photos[0];
        $this->assertSame(self::PNG, base64_encode($photo['photo']->getContents()));
        $this->assertStringContainsString('<b>Инсулин за 31.10</b>', $photo['caption']);
        $this->assertStringContainsString('пик в 13:35 — 62, высокий', $photo['caption']);
        $this->assertStringContainsString('13:00 обед <b>+52</b>: Булочка с повидлом +42, Сахар +9', $photo['caption']);
        $this->assertStringNotContainsString('Чай', $photo['caption']);
        $this->assertStringContainsString('13:20 Прогулка, 60 мин — инсулина меньше на 17%', $photo['caption']);
        $this->assertStringContainsString('Образ жизни: сидячий.', $photo['caption']);
        $this->assertLessThanOrEqual(1024, mb_strlen(strip_tags($photo['caption'])));

        $rows = json_decode($photo['reply_markup'], true)['inline_keyboard'];
        $this->assertSame('insulin_day_2026-10-30', $rows[0][0]['callback_data']);
        $this->assertSame('insulin_lifestyle_2026-10-31', $rows[1][0]['callback_data']);
        $this->assertSame('https://app.example/insulin', $rows[2][0]['web_app']['url']);
    }

    public function test_a_product_name_cannot_break_the_html_of_the_insulin_caption(): void
    {
        $day = $this->insulinDay();
        $day['meals'][0]['items'][0]['name'] = 'Пирог & чай < 5 г';
        $this->diary(fn ($mock) => $mock->shouldReceive('insulinDay')->once()->andReturn($day));

        $this->send(['text' => '/insulin']);

        $this->assertStringContainsString('Пирог &amp; чай &lt; 5 г', $this->photos[0]['caption']);
    }

    public function test_a_long_day_keeps_its_caption_within_the_limit_of_telegram(): void
    {
        $day = $this->insulinDay();
        $day['activities'] = array_fill(0, 30, ['id' => 1, 'title' => str_repeat('Очень долгая прогулка ', 3), 'minute' => 800, 'minutes' => 60, 'calories' => 105, 'effect' => 5]);
        $day['meals'] = array_fill(0, 3, $day['meals'][0]);
        $this->diary(fn ($mock) => $mock->shouldReceive('insulinDay')->once()->andReturn($day));

        $this->send(['text' => '/insulin']);

        $caption = $this->photos[0]['caption'];
        $this->assertLessThanOrEqual(1024, mb_strlen(strip_tags($caption)));
        $this->assertStringContainsString('пик в 13:35', $caption);
        $this->assertStringContainsString('Образ жизни', $caption);
    }

    public function test_the_way_of_life_is_chosen_and_the_chart_is_drawn_again(): void
    {
        $this->diary(function ($mock) {
            $mock->shouldReceive('insulinDay')->once()->with('2026-10-31', 555, 'ru', false)->andReturn(['lifestyle' => 3] + $this->insulinDay());
            $mock->shouldReceive('setLifestyle')->once()->with(1, '2026-10-31', 555, 'ru')->andReturn($this->insulinDay());
            $mock->shouldReceive('insulinDay')->once()->with('2026-10-31', 555, 'ru')->andReturn($this->insulinDay());
        });

        $this->press('insulin_lifestyle_2026-10-31');
        $options = json_decode($this->sent[0]['reply_markup'], true)['inline_keyboard'];
        $this->assertCount(5, $options);
        $this->assertSame('✓ Умеренно активный', $options[2][0]['text']);
        $this->assertSame('insulin_set_1_2026-10-31', $options[0][0]['callback_data']);

        $this->press('insulin_set_1_2026-10-31');
        $this->assertSame(__('calories365-bot.lifestyle_saved', ['lifestyle' => 'Сидячий']), $this->sent[1]['text']);
        $this->assertCount(1, $this->photos);
    }

    public function test_a_day_after_today_is_never_asked_for(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('insulinDay')->once()->with(date('Y-m-d'), 555, 'ru')->andReturn($this->insulinDay()));

        $this->press('insulin_day_2999-01-01');

        $this->assertCount(1, $this->photos);
    }

    public function test_the_insulin_chart_says_so_when_the_diary_does_not_answer(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('insulinDay')->once()->andReturn(['error' => 'timeout']));

        $this->send(['text' => '/insulin']);

        $this->assertSame([], $this->photos);
        $this->assertSame(__('calories365-bot.draft_failed'), $this->sent[0]['text']);
    }

    public function test_the_draft_tells_when_the_meal_was_eaten(): void
    {
        $draft = $this->loadFixture('DiaryAPI/draft.json')['draft'] + ['eaten_time' => '09:00'];
        $this->diary(fn ($mock) => $mock->shouldReceive('draftCollect')->once()
            ->andReturn($this->waiting([['id' => 1, 'kind' => 'text', 'text' => 'в 9 утра']], $draft)));

        $this->send(['text' => 'это было в 9 утра']);

        $this->assertStringContainsString('Съедено в 09:00', $this->sent[0]['text']);
    }

    /**
     * An insulin day as the diary gives it.
     *
     * @return array<string, mixed>
     */
    private function insulinDay(): array
    {
        return [
            'date' => '2026-10-31',
            'lifestyle' => 1,
            'lifestyle_known' => true,
            'base' => 10,
            'zones' => ['medium' => 30, 'high' => 60],
            'now' => ['minute' => 1200, 'level' => 10],
            'peak' => ['minute' => 815, 'level' => 62],
            'meals' => [[
                'part_of_day' => 'dinner', 'minute' => 780, 'time_told' => true, 'rise' => 52,
                'items' => [
                    ['id' => 1, 'name' => 'Булочка с повидлом', 'grams' => 80, 'rise' => 42, 'index_known' => true],
                    ['id' => 2, 'name' => 'Сахар', 'grams' => 10, 'rise' => 9, 'index_known' => true],
                    ['id' => 3, 'name' => 'Чай', 'grams' => 200, 'rise' => 0, 'index_known' => true],
                ],
            ]],
            'activities' => [['id' => 1, 'title' => 'Прогулка', 'minute' => 800, 'minutes' => 60, 'calories' => 105, 'effect' => 17]],
            'estimated_foods' => 0,
            'chart' => self::PNG,
        ];
    }

    public function test_the_statistics_of_a_meal_tell_how_processed_each_product_is(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('showUserStats')->once()->andReturn([
            $this->eaten(['id' => 1, 'name' => 'Хлеб', 'nova' => 3]),
            $this->eaten(['id' => 2, 'name' => 'Банан', 'nova' => 1]),
            $this->eaten(['id' => 3, 'name' => 'Чипсы', 'nova' => 4]),
            $this->eaten(['id' => 4, 'name' => 'Новая еда', 'nova' => null]),
        ]));

        $this->press('Breakfast');

        $this->assertStringStartsWith('Хлеб (100г) — обработанный:', $this->sent[0]['text']);
        $this->assertStringStartsWith('Банан (100г) — натуральный:', $this->sent[1]['text']);
        $this->assertStringStartsWith('Чипсы (100г) — ультрапереработанный:', $this->sent[2]['text']);
        $this->assertStringStartsWith('Новая еда (100г):', $this->sent[3]['text']);
    }

    public function test_the_statistics_of_the_day_list_the_products_of_each_part(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('showUserStats')->once()->andReturn([
            $this->eaten(['id' => 1, 'name' => 'Хлеб', 'nova' => 3]),
            $this->eaten(['id' => 2, 'name' => 'Чипсы', 'nova' => 4, 'part_of_day' => 'supper']),
        ]));

        $this->press('AllDay');

        $text = $this->sent[0]['text'];
        $this->assertStringContainsString('• Хлеб (100г) — обработанный', $text);
        $this->assertStringContainsString('• Чипсы (100г) — ультрапереработанный', $text);
        $this->assertLessThan(strpos($text, 'Чипсы'), strpos($text, 'Хлеб'));
        $this->assertStringContainsString('Итого за день', $text);
    }

    public function test_a_very_long_day_keeps_the_totals_within_the_limit_of_telegram(): void
    {
        $meals = array_map(fn (int $id) => $this->eaten(['id' => $id, 'name' => str_repeat('Очень длинное название ', 6), 'nova' => 4]), range(1, 40));
        $this->diary(fn ($mock) => $mock->shouldReceive('showUserStats')->once()->andReturn($meals));

        $this->press('AllDay');

        $this->assertLessThanOrEqual(4096, mb_strlen($this->sent[0]['text']));
        $this->assertStringContainsString('Итого за день', $this->sent[0]['text']);
    }

    public function test_a_product_name_cannot_break_the_markdown_of_the_statistics(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('showUserStats')->once()->andReturn([
            $this->eaten(['id' => 1, 'name' => 'Чипсы Lay`s', 'nova' => 4]),
            $this->eaten(['id' => 2, 'name' => 'Резинка Wrigley*s_x', 'nova' => 4]),
        ]));

        $this->press('Breakfast');

        $this->assertStringStartsWith('Чипсы Lay\\`s (100г)', $this->sent[0]['text']);
        $this->assertStringStartsWith('Резинка Wrigley\\*s\\_x (100г)', $this->sent[1]['text']);
    }

    public function test_the_statistics_say_so_when_the_diary_does_not_answer(): void
    {
        $this->diary(fn ($mock) => $mock->shouldReceive('showUserStats')->once()->andReturn(['error' => 'timeout']));

        $this->press('Breakfast');

        $this->assertSame(__('calories365-bot.draft_failed'), $this->sent[0]['text']);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function eaten(array $fields): array
    {
        return $fields + ['quantity' => 100, 'part_of_day' => 'morning', 'calories' => 100, 'proteins' => 5, 'fats' => 2, 'carbohydrates' => 10];
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
        $api->shouldReceive('sendPhoto')->andReturnUsing(function (array $p) {
            $this->photos[] = $p;

            return new Message(['message_id' => random_int(1000, 9000), 'chat' => ['id' => $p['chat_id'], 'type' => 'private'], 'date' => time()]);
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
