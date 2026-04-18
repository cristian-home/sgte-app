<?php

namespace Database\Factories;

use App\Models\FuecNumberRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FuecNumberRange>
 */
class FuecNumberRangeFactory extends Factory
{
    protected $model = FuecNumberRange::class;

    public function definition(): array
    {
        $from = fake()->numberBetween(1, 1_000_000);

        return [
            'resolution_number' => fake()->bothify('RES-####'),
            'resolution_year' => (int) fake()->dateTimeBetween('-5 years', 'now')->format('Y'),
            'range_from' => $from,
            'range_to' => $from + fake()->numberBetween(100, 10_000),
            'active' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Only one range may be active at a time; tests that need an
     * active range MUST ensure any existing active ranges have been
     * deactivated or deleted first.
     */
    public function active(): self
    {
        return $this->state(fn () => ['active' => true]);
    }
}
