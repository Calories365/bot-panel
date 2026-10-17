<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The diary asks this endpoint before every voice upload on the site; the bot
 * checks the same Subscription itself. Both must allow the same 11 free uses.
 */
class SubscriptionCheckTest extends TestCase
{
    use RefreshDatabase;

    private const CALORIES_ID = 555;

    public function test_the_last_free_use_is_allowed_and_counted(): void
    {
        Subscription::factory()->create(['user_id' => self::CALORIES_ID, 'counter' => 10, 'premium_until' => null]);

        $this->check()->assertExactJson(['canTranscribeAudio' => true]);

        $this->assertSame(11, $this->counter());
    }

    public function test_a_spent_free_limit_is_refused_without_counting(): void
    {
        Subscription::factory()->create(['user_id' => self::CALORIES_ID, 'counter' => 11, 'premium_until' => null]);

        $this->check()->assertExactJson(['canTranscribeAudio' => false]);

        $this->assertSame(11, $this->counter());
    }

    public function test_a_premium_user_is_allowed_without_counting(): void
    {
        Subscription::factory()->create(['user_id' => self::CALORIES_ID, 'counter' => 30, 'premium_until' => now()->addMonth()]);

        $this->check()->assertExactJson(['canTranscribeAudio' => true]);

        $this->assertSame(30, $this->counter());
    }

    public function test_a_new_user_gets_a_subscription_with_one_use(): void
    {
        $this->check()->assertExactJson(['canTranscribeAudio' => true]);

        $this->assertSame(1, $this->counter());
    }

    public function test_it_requires_the_shared_api_key(): void
    {
        $this->getJson('/api/subscription-check/'.self::CALORIES_ID, ['X-Api-Key' => 'wrong'])->assertForbidden();
    }

    private function check()
    {
        return $this->getJson('/api/subscription-check/'.self::CALORIES_ID, ['X-Api-Key' => 'testing-diary-key'])
            ->assertOk();
    }

    private function counter(): int
    {
        return Subscription::where('user_id', self::CALORIES_ID)->value('counter');
    }
}
