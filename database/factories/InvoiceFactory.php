<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\ThirdParty;
use App\Support\Tz;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'third_party_id' => ThirdParty::factory(),
            'invoice_number' => fake()->unique()->numerify('FAC-####-2026'),
            'total_value' => fake()->randomFloat(2, 100000, 5000000),
            'timezone' => Tz::operation(),
            'issue_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'payment_status' => fake()->randomElement(PaymentStatus::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
