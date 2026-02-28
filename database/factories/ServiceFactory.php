<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $cities = ['Bogota', 'Medellin', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga', 'Pereira', 'Manizales', 'Ibague', 'Villavicencio'];

        return [
            'contract_id' => Contract::factory(),
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'invoice_id' => null,
            'service_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'origin' => fake()->randomElement($cities),
            'destination' => fake()->randomElement($cities),
            'planned_start_time' => fake()->time('H:i'),
            'planned_duration' => fake()->numberBetween(30, 480),
            'actual_start_time' => fake()->optional()->time('H:i'),
            'actual_end_time' => fake()->optional()->time('H:i'),
            'unit_value' => fake()->randomFloat(2, 50000, 500000),
            'quantity' => fake()->numberBetween(1, 5),
            'billing_group' => fake()->optional()->randomElement(['Grupo A', 'Grupo B', 'Grupo C']),
            'payment_method' => fake()->randomElement(['cash', 'credit', 'transfer']),
            'service_status' => fake()->randomElement(['open', 'closed']),
        ];
    }
}
