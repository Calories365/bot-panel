<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\BotUser;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceMessageIntegrationTest3 extends TestCase
{
    use DatabaseTransactions;

    private Bot $bot;

    private BotUser $user;

    private Subscription $subscription;

    /* ---------------------------  bootstrap  ---------------------------- */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');                     // реальное копирование файлов

        $this->seedData();
        $this->fakeTelegram();
        $this->fakeOpenAi();
        $this->fakeDiary();
    }

    /* ---------------------------  test  -------------------------------- */
    /** @test */
    public function voice_pipeline_processes_audio_and_updates_counter(): void
    {
        $payload = json_decode(
            file_get_contents(base_path('tests/Fixtures/Telegram/voice_message_webhook.json')),
            true
        );
        data_set($payload, 'message.from.id', $this->user->telegram_id);
        data_set($payload, 'message.chat.id', $this->user->telegram_id);

        $this->postJson("/api/webhook/bot/{$this->bot->name}", $payload)
            ->assertOk()
            ->assertJson(['status' => 'success']);

        /* --- проверяем, что Whisper и GPT реально вызваны ---- */
        Http::assertSent(fn ($r) => str_contains($r->url(), '/audio/transcriptions')
            && $r['headers']['Content-Type'][0] === 'multipart/form-data');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/chat/completions')
            && str_contains($r->body(), 'куриная грудка'));

        /* --- счетчик подписки вырос на 1 ---- */
        $this->subscription->refresh();
        $this->assertEquals(1, $this->subscription->counter);
    }

    /* --------------------  HTTP FAKES & HELPERS  ----------------------- */
    private function fakeTelegram(): void
    {
        $fileResponse = json_decode(
            file_get_contents(base_path('tests/Fixtures/Telegram/get_file_response.json')),
            true
        );

        Http::fake([
            // getFile
            "api.telegram.org/bot{$this->bot->token}/getFile*" => Http::response($fileResponse, 200),

            // download file
            "api.telegram.org/file/bot{$this->bot->token}/*" => Http::response(
                file_get_contents(base_path('tests/Fixtures/Audio/voice_sample.oga')),
                200,
                ['Content-Type' => 'audio/ogg']
            ),

            // sendMessage
            "api.telegram.org/bot{$this->bot->token}/sendMessage*" => Http::response(['ok' => true], 200),
        ]);
    }

    private function fakeOpenAi(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions*' => Http::response(
                json_decode(file_get_contents(
                    base_path('tests/Fixtures/OpenAI/whisper_ok.json')), true
                ), 200),

            'api.openai.com/v1/chat/completions*' => Http::response(
                json_decode(file_get_contents(
                    base_path('tests/Fixtures/OpenAI/gpt_products_found.json')), true
                ), 200),
        ]);
    }

    private function fakeDiary(): void
    {
        Http::fake([
            config('services.diary.url').'/api/products/search*' => Http::response(
                json_decode(file_get_contents(
                    base_path('tests/Fixtures/Diary/products_found.json')), true
                ), 200),

            config('services.diary.url').'/api/products*' => Http::response(status: 201),
        ]);
    }

    /* ---------------------------  seed  -------------------------------- */
    private function seedData(): void
    {
        $type = BotType::factory()->create(['name' => 'Calories']);

        $this->bot = Bot::factory()->for($type, 'type')->create([
            'name' => 'calories_bot',
            'token' => 'fake_token',
        ]);

        $this->user = BotUser::factory()->create([
            'telegram_id' => fake()->unique()->numberBetween(1_000_000_000, 9_999_999_999),
        ]);
        $this->user->bots()->attach($this->bot->id);

        $this->subscription = Subscription::create([
            'user_id' => $this->user->calories_id,
            'counter' => 0,
        ]);
    }

    /* -------------------------  teardown  ------------------------------ */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
