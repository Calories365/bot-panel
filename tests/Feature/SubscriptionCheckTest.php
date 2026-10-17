<?php

namespace Tests\Feature;

use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The diary asks this endpoint before every voice upload on the site. Voice input
 * has no paid limit; only a spam guard per account, shared with the bot.
 */
class SubscriptionCheckTest extends TestCase
{
    use RefreshDatabase;

    private const CALORIES_ID = 555;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.voice.max_per_hour', 3);
    }

    public function test_a_user_who_spent_the_old_free_limit_can_still_record(): void
    {
        Subscription::factory()->create(['user_id' => self::CALORIES_ID, 'counter' => 50, 'premium_until' => null]);

        $this->check()->assertExactJson(['canTranscribeAudio' => true]);
    }

    public function test_recording_too_often_is_refused(): void
    {
        $this->check()->assertExactJson(['canTranscribeAudio' => true]);
        $this->check()->assertExactJson(['canTranscribeAudio' => true]);
        $this->check()->assertExactJson(['canTranscribeAudio' => true]);

        $this->check()->assertExactJson(['canTranscribeAudio' => false]);
    }

    public function test_the_guard_counts_each_account_separately(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->check();
        }

        $this->getJson('/api/subscription-check/556', ['X-Api-Key' => 'testing-diary-key'])
            ->assertExactJson(['canTranscribeAudio' => true]);
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
}
