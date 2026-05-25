<?php

namespace Database\Factories;

use App\Enums\LicenseCategory;
use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\Municipality;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use App\Models\User;
use App\Support\Tz;
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
            'timezone' => Tz::operation(),
            'license_due_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'eps_id' => Eps::factory(),
            'pension_fund_id' => PensionFund::factory(),
            'severance_fund_id' => SeveranceFund::factory(),
            'has_social_security' => true,
            'active' => true,
        ];
    }

    /**
     * Crea un User vinculado con rol Driver para este Driver. Útil para
     * tests que necesitan ejercitar la regla "User-driver requiere Driver".
     */
    public function withUser(?User $user = null): self
    {
        return $this->afterCreating(function (Driver $driver) use ($user): void {
            $user ??= User::factory()->create([
                'name' => $driver->fullName(),
                'email' => $driver->email,
            ]);
            $user->syncRoles([Role::DRIVER->value]);
            $driver->forceFill(['user_id' => $user->id])->saveQuietly();
        });
    }
}
