<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThirdPartyFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $isNatural = fake()->boolean();

        return [
            'document_type_id' => DocumentType::factory(),
            'identification_number' => fake()->unique()->numerify('##########'),
            'is_natural_person' => $isNatural,
            'first_name' => $isNatural ? fake()->firstName() : null,
            'second_name' => $isNatural ? fake()->optional()->firstName() : null,
            'first_lastname' => $isNatural ? fake()->lastName() : null,
            'second_lastname' => $isNatural ? fake()->optional()->lastName() : null,
            'company_name' => ! $isNatural ? fake()->company() : null,
            'trade_name' => ! $isNatural ? fake()->optional()->companySuffix() : null,
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->unique()->safeEmail(),
            'is_customer' => fake()->boolean(70),
            'is_provider' => fake()->boolean(30),
            'active' => true,
        ];
    }
}
