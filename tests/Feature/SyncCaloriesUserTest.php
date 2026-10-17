<?php

namespace Tests\Feature;

use App\Models\CaloriesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The diary reports every new account here. For an account opened from the Mini
 * App the panel already holds the row: /api/telegram/web-app/link creates it
 * synchronously, before the diary's queued report arrives.
 */
class SyncCaloriesUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_is_stored(): void
    {
        $this->sync(['calories_id' => 77, 'name' => 'Ann', 'email' => 'ann@mail.test'])->assertCreated();

        $this->assertDatabaseHas('calories_users', [
            'calories_id' => 77, 'username_calories' => 'Ann', 'email' => 'ann@mail.test', 'source' => 'calories_only',
        ]);
    }

    public function test_an_account_the_mini_app_already_linked_is_not_stored_twice(): void
    {
        CaloriesUser::create(['calories_id' => 77, 'telegram_id' => 111, 'source' => 'mini_app']);

        $this->sync(['calories_id' => 77, 'name' => 'Ann', 'email' => 'tg111@telegram.test'])->assertSuccessful();

        $this->assertSame(1, CaloriesUser::where('calories_id', 77)->count());
        $this->assertDatabaseHas('calories_users', [
            'calories_id' => 77, 'telegram_id' => 111, 'source' => 'mini_app', 'email' => 'tg111@telegram.test',
        ]);
    }

    private function sync(array $payload)
    {
        return $this->postJson('/api/sync-calories-user', ['payload' => $payload], ['X-Api-Key' => 'testing-diary-key']);
    }
}
