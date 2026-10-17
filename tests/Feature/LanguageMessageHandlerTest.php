<?php

namespace Tests\Feature;

use App\Models\LanguageSetting;
use App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers\LanguageMessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;

class LanguageMessageHandlerTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    public function test_it_offers_russian_when_the_setting_was_never_saved(): void
    {
        $this->assertSame(0, LanguageSetting::count());

        $this->handle('calories365_bot');

        $this->assertSame(['Russian', 'Ukrainian', 'English'], $this->offeredLanguages());
    }

    public function test_it_hides_russian_when_the_setting_turns_it_off(): void
    {
        LanguageSetting::create(['russian_language_enabled' => false]);

        $this->handle('calories365_bot');

        $this->assertSame(['Ukrainian', 'English'], $this->offeredLanguages());
    }

    public function test_the_university_bot_never_offers_russian(): void
    {
        $this->handle('calories365KNU_bot');

        $this->assertSame(['Ukrainian', 'English'], $this->offeredLanguages());
    }

    private function handle(string $botName): void
    {
        $telegram = Mockery::mock(Api::class);
        $telegram->shouldReceive('sendMessage')->andReturnUsing(function (array $params) {
            $this->sent[] = $params;

            return new Message(['message_id' => 1]);
        });

        $message = new Message([
            'message_id' => 10,
            'from' => ['id' => 111, 'is_bot' => false, 'first_name' => 'Test', 'language_code' => 'ru'],
            'chat' => ['id' => 111, 'type' => 'private', 'first_name' => 'Test'],
            'text' => '/language',
        ]);

        app(LanguageMessageHandler::class)->handle((object) ['name' => $botName], $telegram, $message);
    }

    /**
     * @return array<int, string>
     */
    private function offeredLanguages(): array
    {
        $keyboard = json_decode((string) $this->sent[0]['reply_markup'], true);

        return array_column($keyboard['inline_keyboard'][0], 'callback_data');
    }
}
