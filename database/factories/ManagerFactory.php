<?php

namespace Database\Factories;

use App\Models\Manager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Manager>
 */
class ManagerFactory extends Factory
{
    protected $model = Manager::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'telegram_id' => $this->faker->numerify('#########'),
            'is_last' => false,
        ];
    }

    /**
     * Indicate that the manager is last.
     */
    public function last(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_last' => true,
        ]);
    }
}
