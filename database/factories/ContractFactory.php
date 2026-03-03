<?php

namespace Database\Factories;

use App\Enums\ContractObject;
use App\Models\ThirdParty;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-6 months', '+1 month');

        return [
            'contract_number' => fake()->unique()->numerify('CT-####-2026'),
            'third_party_id' => ThirdParty::factory(),
            'contract_object' => fake()->randomElement(ContractObject::cases()),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+2 years'),
            'route_description' => fake()->sentence(6),
            'is_generic' => fake()->boolean(20),
            'active' => true,
        ];
    }
}
