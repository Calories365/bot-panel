<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotAccess;
use App\Models\BotType;
use App\Services\AudioConversionService;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;

/**
 * The transcription bot answers a voice message with its text, as a reply to
 * the voice message.
 */
class TranscriptionBotTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret';

    private array $sent = [];

    private array $actions = [];

    private ?string $transcript = 'Привет, это проверка голосового';

    private ?bool $withLanguageHint = null;

    private bool $granted = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->partialMock(AudioConversionService::class, fn ($m) => $m->shouldReceive('processAudioMessage')
            ->andReturnUsing(function ($telegram, $bot, $message, bool $withLanguageHint = true) {
                $this->withLanguageHint = $withLanguageHint;

                return $this->transcript;
            }));

        $this->bindTelegramApiMock();
    }

    public function test_a_voice_message_is_answered_with_its_text(): void
    {
        $this->sendUpdate($this->voicePayload());

        $this->assertSame(['typing'], $this->actions);
        $this->assertCount(1, $this->sent);
        $this->assertSame(111111, $this->sent[0]['chat_id']);
        $this->assertSame('Привет, это проверка голосового', $this->sent[0]['text']);
        $this->assertSame(100, json_decode($this->sent[0]['reply_parameters'], true)['message_id']);
        $this->assertFalse($this->withLanguageHint);
    }

    public function test_a_long_text_is_split_into_messages_telegram_accepts(): void
    {
        $this->transcript = str_repeat('слово ', 1000);

        $this->sendUpdate($this->voicePayload());

        $this->assertCount(2, $this->sent);
        $this->assertSame(4096, mb_strlen($this->sent[0]['text']));
        $this->assertSame($this->transcript, $this->sent[0]['text'].$this->sent[1]['text']);
    }

    public function test_an_unrecognized_voice_message_is_reported_in_the_users_language(): void
    {
        $this->transcript = null;
        $payload = $this->voicePayload();
        $payload['message']['from']['language_code'] = 'uk';

        $this->sendUpdate($payload);

        $this->assertCount(1, $this->sent);
        $this->assertSame('Не вдалося розпізнати голосове повідомлення. Спробуйте ще раз.', $this->sent[0]['text']);
    }

    public function test_a_user_without_the_password_is_asked_for_it_instead_of_the_text(): void
    {
        $this->granted = false;

        $this->sendUpdate($this->voicePayload());

        $this->assertNull($this->withLanguageHint);
        $this->assertCount(1, $this->sent);
        $this->assertSame('Введите пароль, чтобы пользоваться ботом.', $this->sent[0]['text']);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->granted = false;

        $this->sendUpdate($this->textPayload('не тот пароль'));

        $this->assertSame('Неверный пароль. Попробуйте ещё раз.', $this->sent[0]['text']);
        $this->assertFalse(BotAccess::query()->exists());
    }

    public function test_the_password_lets_the_user_in_for_good(): void
    {
        $this->granted = false;
        config()->set('services.transcription_bot.password_hash', bcrypt('golos-test'));

        $bot = $this->sendUpdate($this->textPayload(' golos-test '));

        $this->assertSame('Готово. Присылайте голосовые, я отвечу их текстом.', $this->sent[0]['text']);
        $this->assertTrue(BotAccess::granted($bot->id, 111111));

        $payload = $this->voicePayload();
        $payload['update_id']++;
        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();

        $this->assertSame('Привет, это проверка голосового', end($this->sent)['text']);
    }

    public function test_start_greets_and_asks_for_the_password(): void
    {
        $this->granted = false;

        $this->sendUpdate($this->textPayload('/start'));

        $this->assertCount(2, $this->sent);
        $this->assertSame('Введите пароль, чтобы пользоваться ботом.', $this->sent[1]['text']);
    }

    public function test_a_text_message_gets_a_hint_to_send_a_voice_message(): void
    {
        $this->sendUpdate($this->textPayload('привет'));

        $this->assertCount(1, $this->sent);
        $this->assertSame('Пришлите голосовое сообщение, и я отвечу его текстом.', $this->sent[0]['text']);
        $this->assertNull($this->withLanguageHint);
    }

    private function sendUpdate(array $payload): Bot
    {
        $type = BotType::factory()->create(['name' => 'Transcription']);

        $bot = Bot::factory()->active()->create([
            'name' => 'TranscriptionTest_bot',
            'type_id' => $type->id,
            'secret_token' => hash('sha256', self::SECRET),
        ]);

        if ($this->granted) {
            BotAccess::create(['bot_id' => $bot->id, 'telegram_id' => 111111]);
        }

        $this->postJson(route('bot.webhook.handle', ['bot' => $bot->name]), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();

        return $bot;
    }

    private function textPayload(string $text): array
    {
        $payload = $this->voicePayload();
        unset($payload['message']['voice']);
        $payload['message']['text'] = $text;

        return $payload;
    }

    private function voicePayload(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/Telegram/voice_webhook.json')),
            true, 512, JSON_THROW_ON_ERROR
        );
    }

    private function bindTelegramApiMock(): void
    {
        $api = Mockery::mock(Api::class);

        $api->shouldReceive('sendChatAction')
            ->andReturnUsing(function (array $p) {
                $this->actions[] = $p['action'];

                return true;
            });

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
