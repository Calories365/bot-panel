<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\BotUser;
use App\Services\TelegramServices\TelegramHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Telegram\Bot\Api;
use Tests\TestCase;

class MyChatMemberUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const TELEGRAM_ID = 222222;

    public static function botTypes(): array
    {
        return [
            'calories bot, behind the auth middleware' => [6, 'Calories'],
            'default bot, no middleware' => [1, 'Default'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('botTypes')]
    public function test_blocking_the_bot_is_recorded(int $typeId, string $typeName): void
    {
        [$bot, $user] = $this->botAndUser($typeId, $typeName);

        $this->dispatch($bot, 'kicked');

        $this->assertTrue($user->banned_bots()->whereKey($bot->id)->exists());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('botTypes')]
    public function test_unblocking_the_bot_removes_the_record(int $typeId, string $typeName): void
    {
        [$bot, $user] = $this->botAndUser($typeId, $typeName);
        $user->banned_bots()->attach($bot->id);

        $this->dispatch($bot, 'member');

        $this->assertFalse($user->banned_bots()->whereKey($bot->id)->exists());
    }

    /**
     * @return array{0: Bot, 1: BotUser}
     */
    private function botAndUser(int $typeId, string $typeName): array
    {
        BotType::factory()->create(['id' => $typeId, 'name' => $typeName]);
        $bot = Bot::factory()->active()->create(['name' => 'member_test_bot', 'type_id' => $typeId]);
        $user = BotUser::factory()->create(['telegram_id' => self::TELEGRAM_ID, 'calories_id' => 555]);

        return [$bot, $user];
    }

    private function dispatch(Bot $bot, string $newStatus): void
    {
        $api = Mockery::mock(Api::class);
        $api->shouldIgnoreMissing();

        $request = new Request;
        $request->replace([
            'update_id' => random_int(1, 1_000_000),
            'my_chat_member' => [
                'chat' => ['id' => self::TELEGRAM_ID, 'type' => 'private', 'first_name' => 'Test'],
                'from' => ['id' => self::TELEGRAM_ID, 'is_bot' => false, 'first_name' => 'Test'],
                'date' => time(),
                'old_chat_member' => ['status' => 'member', 'user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot']],
                'new_chat_member' => ['status' => $newStatus, 'user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot']],
            ],
        ]);

        (new TelegramHandler(app(), fn () => $api))->handle($bot->name, $request);
    }
}
