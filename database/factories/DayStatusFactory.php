<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DayStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['projected', 'executed']);

        return [
            'date' => fake()->unique()->date(),
            'status' => $status,
            'executor_id' => $status === 'executed' ? User::factory() : null,
            'executed_at' => $status === 'executed' ? fake()->dateTime() : null,
        ];
    }
}
