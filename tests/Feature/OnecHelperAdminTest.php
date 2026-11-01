<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Telegram\Bot\Api;
use Tests\TestCase;

/**
 * The 1C helper bot is set up in the panel as any other bot. The `overload:`
 * mock declares Telegram\Bot\Api for the whole process, so these tests run in
 * processes of their own.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class OnecHelperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_puts_the_type_among_the_bot_types_of_the_panel(): void
    {
        // The schema dump marks the migration as run: the other tests create
        // the types they need with ids of their own.
        (require database_path('migrations/2026_11_01_120000_add_the_onec_helper_bot_type.php'))->up();
        Sanctum::actingAs(User::factory()->create());

        $types = $this->getJson('/api/bot-types')->assertOk()->json('types');

        $this->assertContains('OnecHelper', array_column($types, 'name'));
    }

    public function test_saving_the_bot_with_a_secret_sets_its_webhook(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $type = BotType::factory()->create(['name' => 'OnecHelper']);
        $bot = Bot::factory()->create(['name' => 'YellowHelperAIBot', 'type_id' => $type->id]);

        $api = Mockery::mock('overload:'.Api::class);
        $api->shouldReceive('setWebhook')->once()->with([
            'url' => 'https://bot-panel.example/api/webhook/bot/YellowHelperAIBot',
            'secret_token' => 'webhook-secret',
        ])->andReturn(true);

        $this->postJson("/api/bots/update/{$bot->id}", [
            'name' => 'YellowHelperAIBot',
            'token' => '123456:telegram-token',
            'secret_token' => 'webhook-secret',
            'active' => true,
            'web_hook' => 'https://bot-panel.example',
            'type_id' => $type->id,
        ])->assertOk();

        $this->assertSame(hash('sha256', 'webhook-secret'), $bot->fresh()->secret_token);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
