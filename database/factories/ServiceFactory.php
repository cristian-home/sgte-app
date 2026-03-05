<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::inRandomOrder()->first()->id ?? Contract::factory(),
            'vehicle_id' => Vehicle::inRandomOrder()->first()->id ?? Vehicle::factory(),
            'driver_id' => Driver::inRandomOrder()->first()->id ?? Driver::factory(),
            'invoice_id' => null,
            'service_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'origin_municipality_id' => Municipality::inRandomOrder()->first()?->id ?? Municipality::factory(),
            'origin_address' => fake()->optional()->streetAddress(),
            'origin_coordinates' => null,
            'destination_municipality_id' => Municipality::inRandomOrder()->first()?->id ?? Municipality::factory(),
            'destination_address' => fake()->optional()->streetAddress(),
            'destination_coordinates' => null,
            'planned_start_time' => fake()->time('H:i'),
            'planned_duration' => fake()->numberBetween(30, 480),
            'actual_start_time' => fake()->optional()->time('H:i'),
            'actual_end_time' => fake()->optional()->time('H:i'),
            'unit_value' => fake()->randomFloat(2, 50000, 500000),
            'quantity' => fake()->numberBetween(1, 5),
            'billing_group' => fake()->optional()->randomElement(['Grupo A', 'Grupo B', 'Grupo C']),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'service_status' => fake()->randomElement(ServiceStatus::cases()),
        ];
    }
}
