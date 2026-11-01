<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use Closure;
use InvalidArgumentException;

class PaymentGateways
{
    public const int MIN_CENTS = SplitPlan::MIN_CENTS;

    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    /**
     * @param  array<string, Closure(): PaymentGateway>  $factories  keyed by PaymentMethod value
     */
    public function __construct(
        private readonly array $factories,
        private readonly bool $everythingAvailable = false,
    ) {}

    public function for(PaymentMethod $method): PaymentGateway
    {
        if (! $method->isHosted() || ! isset($this->factories[$method->value])) {
            throw new InvalidArgumentException("No payment gateway for [{$method->value}].");
        }

        return $this->resolved[$method->value] ??= ($this->factories[$method->value])();
    }

    /**
     * @return list<PaymentMethod>
     */
    public function available(): array
    {
        if ($this->everythingAvailable) {
            return PaymentMethod::hosted();
        }

        $available = [];

        if (config('services.stripe.secret')) {
            $available[] = PaymentMethod::Stripe;
        }

        if (config('services.paypal.client_id') && config('services.paypal.secret')) {
            $available[] = PaymentMethod::PayPal;
        }

        return $available;
    }

    /**
     * Replaces the registry with one where both hosted methods are available
     * and resolve to a single fake gateway.
     */
    public static function fake(): FakePaymentGateway
    {
        $fake = new FakePaymentGateway;

        app()->instance(self::class, new self([
            PaymentMethod::Stripe->value => fn (): PaymentGateway => $fake,
            PaymentMethod::PayPal->value => fn (): PaymentGateway => $fake,
        ], everythingAvailable: true));

        return $fake;
    }
}
