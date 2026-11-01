<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentGateways;
use App\Payments\PaymentRequest;
use App\Payments\StartedPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Opens a pending top-up payment with the provider (spec 2.4). The wallet is
 * credited later, by CompletePayment, once the provider confirms the money.
 * If the provider throws, the payment row is rolled back with it.
 */
class StartTopUpPayment
{
    public const int MIN_CENTS = 500;

    public const int MAX_CENTS = 50000;

    public function __construct(private readonly PaymentGateways $gateways) {}

    /**
     * @throws InvalidArgumentException
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(User $user, Money $amount, PaymentMethod $method): StartedPayment
    {
        if ($amount->cents() < self::MIN_CENTS || $amount->cents() > self::MAX_CENTS) {
            throw new InvalidArgumentException('Top-up must be between $5.00 and $500.00.');
        }

        return DB::transaction(function () use ($user, $amount, $method): StartedPayment {
            $payment = Payment::create([
                'user_id' => $user->id,
                'purpose' => PaymentPurpose::TopUp,
                'order_id' => null,
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount' => $amount,
                'currency' => 'usd',
            ]);

            $started = $this->gateways->for($method)->start($payment, PaymentRequest::single(
                'Cartopia wallet top-up',
                $amount,
                'Cartopia wallet top-up',
                route('payments.return', $payment),
                route('payments.cancel', $payment),
            ));

            $payment->update(['provider_reference' => $started->reference]);

            return $started;
        });
    }
}
