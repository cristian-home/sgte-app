<?php

namespace Database\Factories;

use App\Models\IncidentType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $affectsBilling = fake()->boolean(20);

        return [
            'service_id' => Service::factory(),
            'incident_type_id' => IncidentType::inRandomOrder()->first()->id ?? IncidentType::factory(),
            'description' => fake()->sentence(8),
            'registrar_id' => User::factory(),
            'is_driver_report' => fake()->boolean(),
            'reported_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'affects_billing' => $affectsBilling,
            'additional_value' => $affectsBilling ? fake()->randomFloat(2, 10000, 200000) : null,
        ];
    }
}
