<?php

namespace Database\Factories;

use App\Enums\DayStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DayStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $status = fake()->randomElement(DayStatusEnum::cases());

        return [
            'date' => fake()->unique()->date(),
            'status' => $status,
            'executor_id' => $status === DayStatusEnum::Executed ? User::factory() : null,
            'executed_at' => $status === DayStatusEnum::Executed ? fake()->dateTime() : null,
        ];
    }
}
