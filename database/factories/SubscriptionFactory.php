<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->unique()->numberBetween(1000, 9999),
            'counter' => fake()->numberBetween(0, 10),
            'premium_until' => fake()->optional(0.3)->dateTimeBetween('now', '+1 year'),
        ];
    }

    /**
     * Indicate that the subscription is premium.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'premium_until' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the subscription has reached the free limit.
     */
    public function freeLimitReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'counter' => 11,
            'premium_until' => null,
        ]);
    }

    /**
     * Indicate that the subscription is new (no usage).
     */
    public function new(): static
    {
        return $this->state(fn (array $attributes) => [
            'counter' => 0,
            'premium_until' => null,
        ]);
    }
}
