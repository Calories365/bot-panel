<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\BotType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Telegram\Bot\Api;
use Tests\TestCase;

class BotCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mockery::mock('overload:'.Api::class)
            ->shouldReceive('setWebhook')
            ->andReturnTrue();
    }

    /** @test */
    public function authorized_user_can_get_bots_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bots')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'token', 'message', 'active', 'web_hook'],
                ],
            ]);
    }

    /** @test */
    public function can_get_specific_bot_info(): void
    {
        $user = User::factory()->create();
        $botType = BotType::factory()->create();
        $bot = Bot::factory()->for($botType, 'type')->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/bots/{$bot->id}")
            ->assertOk()
            ->assertJsonPath('id', $bot->id)
            ->assertJsonPath('name', $bot->name);
    }

    /** @test */
    public function authorized_user_can_create_bot(): void
    {
        $user = User::factory()->create();
        $botType = BotType::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Bot '.Str::uuid(),
            'token' => 'token_'.Str::uuid(),
            'message' => 'Test message',
            'active' => true,
            'web_hook' => 'https://example.com/webhook/'.Str::uuid(),
            'type_id' => $botType->id,
        ];

        $this->postJson('/api/bots/create', $payload)
            ->assertOk()
            ->assertJsonStructure(['id']);

        $this->assertDatabaseHas('bots', [
            'name' => $payload['name'],
            'token' => $payload['token'],
        ]);
    }

    /** @test */
    public function authorized_user_can_update_bot(): void
    {
        $user = User::factory()->create();
        $botType = BotType::factory()->create();
        $bot = Bot::factory()->for($botType, 'type')->create();

        Sanctum::actingAs($user);

        $updated = [
            'name' => 'Updated '.Str::uuid(),
            'token' => 'upd_'.Str::uuid(),
            'message' => 'Updated message',
            'active' => false,
            'web_hook' => 'https://example.com/upd/'.Str::uuid(),
            'type_id' => $botType->id,
        ];

        $this->postJson("/api/bots/update/{$bot->id}", $updated)
            ->assertOk()
            ->assertJsonPath('name', $updated['name']);

        $this->assertDatabaseHas('bots', [
            'id' => $bot->id,
            'name' => $updated['name'],
            'token' => $updated['token'],
            'active' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
