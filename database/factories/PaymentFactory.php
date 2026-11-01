<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => PaymentPurpose::TopUp,
            'order_id' => null,
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Pending,
            'amount' => fake()->numberBetween(500, 20000),
            'currency' => 'usd',
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state([
            'user_id' => $order->user_id,
            'purpose' => PaymentPurpose::Order,
            'order_id' => $order->id,
            'amount' => $order->total->subtract($order->wallet_amount)->cents(),
        ]);
    }

    public function topUp(int $cents): static
    {
        return $this->state([
            'purpose' => PaymentPurpose::TopUp,
            'order_id' => null,
            'amount' => $cents,
        ]);
    }

    public function stripe(): static
    {
        return $this->state(['method' => PaymentMethod::Stripe]);
    }

    public function paypal(): static
    {
        return $this->state(['method' => PaymentMethod::PayPal]);
    }

    public function withReference(string $reference): static
    {
        return $this->state(['provider_reference' => $reference]);
    }

    public function succeeded(): static
    {
        return $this->state([
            'status' => PaymentStatus::Succeeded,
            'completed_at' => now(),
        ]);
    }
}
