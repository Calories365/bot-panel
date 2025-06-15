<?php

namespace Database\Factories;

use App\Models\BotUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BotUser>
 */
class BotUserFactory extends Factory
{
    protected $model = BotUser::class;

    /**
     * Состояние по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'username' => $this->faker->unique()->userName,
            'telegram_id' => $this->faker->unique()->numberBetween(10_000, 9_999_999_999),
            'premium' => false,
            'is_banned' => false,
            'phone' => $this->faker->optional()->e164PhoneNumber,
            'calories_id' => $this->faker->optional()->numberBetween(1_000, 9_999),
            'locale' => $this->faker->randomElement(['ru', 'ua', 'en']),
            'last_active_at' => $this->faker->optional()->dateTimeThisYear,
            'source' => $this->faker->optional()->randomElement(['bot_only', 'bot_link', 'calories']),
        ];
    }

    /**
     * Премиум-пользователь.
     */
    public function premium(): static
    {
        return $this->state(fn () => ['premium' => true]);
    }

    /**
     * Забаненный пользователь.
     */
    public function banned(): static
    {
        return $this->state(fn () => ['is_banned' => true]);
    }

    /**
     * Задать конкретную локаль.
     */
    public function locale(string $locale): static
    {
        return $this->state(fn () => ['locale' => $locale]);
    }
}
