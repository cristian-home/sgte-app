<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EpsFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->unique()->words(3, true),
        ];
    }
}
