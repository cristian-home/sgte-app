<?php

namespace Database\Factories;

use App\Enums\FuecStatus;
use App\Models\FuecNumberRange;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FuecFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'service_id' => Service::factory(),
            'fuec_number_range_id' => FuecNumberRange::factory(),
            'consecutive_number' => fake()->unique()->numberBetween(1, 99_999),
            'generated_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'qr_code' => (string) Str::uuid(),
            'status' => FuecStatus::Active,
            'pdf_path' => null,
            'pdf_disk' => 's3',
        ];
    }

    public function cancelled(): self
    {
        return $this->state(fn () => ['status' => FuecStatus::Cancelled]);
    }
}
