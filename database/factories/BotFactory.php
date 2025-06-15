<?php

namespace Database\Factories;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bot>
 */
class BotFactory extends Factory
{
    protected $model = Bot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'calories1',
            'token' => $this->faker->sha256,
            'message' => $this->faker->text(200),
            'active' => 1,
            'message_image' => null,
            'type_id' => 6,
            'wordpress_endpoint' => $this->faker->url,
            'web_hook' => $this->faker->url,
            'video_ru' => null,
            'video_ua' => null,
            'video_eng' => null,
        ];
    }

    /**
     * Indicate that the bot is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the bot is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
