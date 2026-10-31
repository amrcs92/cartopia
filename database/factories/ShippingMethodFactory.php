<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'fee' => fake()->numberBetween(0, 2000),
        ];
    }

    public function pickup(): static
    {
        return $this->state(['name' => 'Pickup', 'fee' => 0]);
    }

    public function ups(): static
    {
        return $this->state(['name' => 'UPS', 'fee' => 500]);
    }
}
