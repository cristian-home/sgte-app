<?php

namespace Database\Factories;

use App\Models\BillingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingGroup>
 */
class BillingGroupFactory extends Factory
{
    protected $model = BillingGroup::class;

    public function definition(): array
    {
        $code = strtolower($this->faker->unique()->word());

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'active' => true,
            'description' => null,
        ];
    }
}
