<?php

namespace Database\Factories;

use App\Enums\BillingUnitType;
use App\Enums\ContractObject;
use App\Models\ThirdParty;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    public function definition(): array
    {
        $tz = Tz::operation();
        $startDate = fake()->dateTimeBetween('-6 months', '+1 month')->format('Y-m-d');
        $endDate = CarbonImmutable::parse($startDate, $tz)
            ->addMonths(fake()->numberBetween(6, 24))
            ->format('Y-m-d');

        return [
            'contract_number' => fake()->unique()->numerify('CT-####-2026'),
            'third_party_id' => ThirdParty::factory(),
            'contract_object' => fake()->randomElement(ContractObject::cases()),
            'timezone' => $tz,
            'start_at' => Tz::startOfDayInTzAsUtc($startDate, $tz),
            'end_at' => Tz::endOfDayInTzAsUtc($endDate, $tz),
            'route_description' => fake()->sentence(6),
            'is_generic' => fake()->boolean(20),
            'active' => true,
            'billing_unit_type' => fake()->randomElement(BillingUnitType::cases()),
        ];
    }
}
