<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\BotUser;
use App\Models\Subscription;
use App\Services\AudioConversionService;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;

class VoiceMessageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private array $sent = [];

    /* ----------------------------------------------------------------- */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app()->setLocale('ru');

        $this->mockAudioConversionService();
        $this->mockDiaryApi();
        $this->fakeExternalHttp();
        $this->bindTelegramApiMock();
    }

    /* ----------------------------------------------------------------- */
    /** @test */
    /** @test */
    public function voice_webhook_returns_success(): void
    {
        [$bot, $payload] = $this->prepareBotAndPayload();

        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])
            ->assertOk()
            ->assertExactJson(['status' => 'success']);

        $this->assertCount(2, $this->sent);
        $this->assertEquals([111111, 111111], array_column($this->sent, 'chat_id'));

        $card = $this->sent[0]['text'];

        $this->assertStringContainsString('Вы сказали: Творог', $card);
        $this->assertStringContainsString('| Параметр | 100г | 225г |', $card);
        $this->assertStringContainsString('| Калории  | 136  | 306', $card);
        $this->assertStringContainsString('| Белки', $card);

        $kb = json_decode($this->sent[1]['reply_markup'], true);
        $flat = array_map(fn ($row) => array_column($row, 'callback_data'), $kb['inline_keyboard']);
        $this->assertEquals(
            [['save_morning', 'save_dinner'], ['save_supper', 'cancel']],
            $flat
        );

        $this->assertTrue(Cache::has('user_products_111111'));
        $this->assertArrayHasKey(24794, Cache::get('user_products_111111'));
    }

    public function test_a_user_who_spent_the_old_free_limit_still_gets_an_answer(): void
    {
        [$bot, $payload] = $this->prepareBotAndPayload();
        Subscription::factory()->create(['user_id' => 555, 'counter' => 50, 'premium_until' => null]);

        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();

        $this->assertStringContainsString('Творог', $this->sent[0]['text']);
    }

    public function test_too_many_voice_messages_in_a_row_are_refused(): void
    {
        config()->set('services.voice.max_per_hour', 1);
        [$bot, $payload] = $this->prepareBotAndPayload();
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET];

        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, $headers)->assertOk();
        $answered = count($this->sent);

        $payload['update_id']++;
        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, $headers)->assertOk();

        $this->assertCount($answered + 1, $this->sent);
        $this->assertStringContainsString(
            trim(explode(':minutes', __('calories365-bot.voice_rate_limited'))[0]),
            end($this->sent)['text']
        );
    }

    /* ----------------------------------------------------------------- */
    private function prepareBotAndPayload(): array
    {
        BotType::factory()->create(['id' => 6, 'name' => 'Calories']);

        $bot = Bot::factory()->active()->create([
            'name' => 'Calories365Test_bot',
            'secret_token' => hash('sha256', self::SECRET),
        ]);

        BotUser::factory()->locale('ru')->create([
            'bot_id' => $bot->id,
            'telegram_id' => 111111,
            'calories_id' => 555,
        ]);

        Subscription::factory()->premium()->create();

        $payload = json_decode(
            file_get_contents(base_path('tests/Fixtures/Telegram/voice_webhook.json')),
            true, 512, JSON_THROW_ON_ERROR
        );

        return [$bot, $payload];
    }

    /* ----------------- Telegram Api with factory -------------------- */
    private function bindTelegramApiMock(): void
    {
        $api = Mockery::mock(Api::class);

        $api->shouldReceive('getFile')
            ->andReturn((object) ['file_path' => 'voice/file_11.oga']);

        $api->shouldReceive('sendMessage')
            ->andReturnUsing(function (array $p) {
                $this->sent[] = $p;

                return new Message([
                    'message_id' => random_int(1e3, 9e3),
                    'chat' => ['id' => $p['chat_id'], 'type' => 'private'],
                    'date' => time(),
                    'text' => 'stub',
                ]);
            });

        $this->app->extend(TelegramHandler::class, function ($h) use ($api) {
            $ref = new \ReflectionProperty($h, 'apiFactory');
            $ref->setAccessible(true);
            $ref->setValue($h, fn () => $api);

            return $h;
        });
    }

    /* ------------------- AudioConversionService ---------------------- */
    private function mockAudioConversionService(): void
    {
        $this->partialMock(AudioConversionService::class, fn ($m) => $m->shouldReceive('processAudioMessage')->andReturn('Творог - 225 грамм'));
    }

    /* ----------------------- Diary API ------------------------------- */
    private function mockDiaryApi(): void
    {
        $json = json_decode(
            file_get_contents(base_path('tests/Fixtures/DiaryAPI/products_found.json')),
            true, 512, JSON_THROW_ON_ERROR
        );

        $this->partialMock(DiaryApiService::class,
            fn ($m) => $m->shouldReceive('sendText')->andReturn($json));
    }

    /* -------------------- HTTP ------------------------------- */
    private function fakeExternalHttp(): void
    {
        $oga = file_get_contents(base_path('tests/Fixtures/Audio/file.oga'));
        $wh = file_get_contents(base_path('tests/Fixtures/OpenAI/whisper_success.json'));
        $gpt = file_get_contents(base_path('tests/Fixtures/OpenAI/gpt_food_found.json'));

        Http::fake([
            'api.telegram.org/file/bot*' => Http::response($oga, 200),
            '*audio/transcriptions*' => Http::response($wh, 200),
            '*chat/completions*' => Http::response($gpt, 200),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
