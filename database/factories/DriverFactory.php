<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Models\DocumentType;
use App\Models\Eps;
use App\Models\Municipality;
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
            'municipality_id' => Municipality::inRandomOrder()->first()?->id ?? Municipality::factory(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('3#########'),
            'email' => fake()->unique()->safeEmail(),
            // Default to C3 (the most permissive category) so factory-created
            // drivers are always compatible with every vehicle type. Tests that
            // need a specific category or an expired license can override.
            'license_category' => LicenseCategory::C3,
            'license_due_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'eps_id' => Eps::factory(),
            'pension_fund_id' => PensionFund::factory(),
            'severance_fund_id' => SeveranceFund::factory(),
            'has_social_security' => true,
            'active' => true,
        ];
    }
}
