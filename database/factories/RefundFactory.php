<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_id' => null,
            'admin_id' => User::factory()->admin(),
            'reason' => fake()->sentence(),
            'provider_amount' => 0,
            'wallet_amount' => 0,
            'status' => RefundStatus::Pending,
            'attempt' => 1,
        ];
    }
}
