<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\DocumentType;
use App\Models\Eps;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory(),
            'identification_number' => fake()->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'second_name' => fake()->optional()->firstName(),
            'first_lastname' => fake()->lastName(),
            'second_lastname' => fake()->optional()->lastName(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->unique()->safeEmail(),
            'license_category' => fake()->randomElement(LicenseCategory::cases()),
            'license_due_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'eps_id' => Eps::factory(),
            'pension_fund_id' => PensionFund::factory(),
            'severance_fund_id' => SeveranceFund::factory(),
            'has_social_security' => true,
            'active' => true,
        ];
    }
}
