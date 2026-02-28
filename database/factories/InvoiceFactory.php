<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_number' => fake()->unique()->numerify('FAC-####-2026'),
            'total_value' => fake()->randomFloat(2, 100000, 5000000),
            'issue_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'overdue']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
