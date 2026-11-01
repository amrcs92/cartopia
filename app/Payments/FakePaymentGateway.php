<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\PaymentOutcome;
use App\Payments\Outcomes\Succeeded;
use Throwable;

class FakePaymentGateway implements PaymentGateway
{
    /** @var list<array{0: Payment, 1: PaymentRequest}> */
    public array $started = [];

    /** @var list<int> */
    public array $confirmed = [];

    /** @var list<int> */
    public array $cancelled = [];

    /** @var list<int> */
    public array $lookedUp = [];

    /** @var list<PaymentOutcome> */
    private array $confirmOutcomes = [];

    /** @var list<PaymentOutcome|Throwable> */
    private array $lookupOutcomes = [];

    private ?PaymentOutcome $cancelOutcome = null;

    private bool $failNextStart = false;

    private bool $redirectToCancel = false;

    public function willConfirm(PaymentOutcome ...$outcomes): static
    {
        array_push($this->confirmOutcomes, ...$outcomes);

        return $this;
    }

    /**
     * Queues what the next lookups answer; a Throwable is thrown instead.
     * Once the queue is empty, a lookup reports nothing paid.
     */
    public function willLookUp(PaymentOutcome|Throwable ...$outcomes): static
    {
        array_push($this->lookupOutcomes, ...$outcomes);

        return $this;
    }

    public function willCancelWith(PaymentOutcome $outcome): static
    {
        $this->cancelOutcome = $outcome;

        return $this;
    }

    public function failToStart(): static
    {
        $this->failNextStart = true;

        return $this;
    }

    public function redirectToCancel(): static
    {
        $this->redirectToCancel = true;

        return $this;
    }

    public function start(Payment $payment, PaymentRequest $request): StartedPayment
    {
        if ($this->failNextStart) {
            $this->failNextStart = false;

            throw new PaymentProviderUnavailable('The fake gateway is set to fail.');
        }

        $this->started[] = [$payment, $request];

        $route = $this->redirectToCancel ? 'payments.cancel' : 'payments.return';

        return new StartedPayment("fake_{$payment->id}", route($route, $payment));
    }

    public function confirm(Payment $payment): PaymentOutcome
    {
        $this->confirmed[] = $payment->id;

        if ($this->confirmOutcomes !== []) {
            return array_shift($this->confirmOutcomes);
        }

        return $this->paid($payment);
    }

    /**
     * What the fake answers for a payment that went through.
     */
    public function paid(Payment $payment): Succeeded
    {
        $isCard = $payment->method === PaymentMethod::Stripe;

        return new Succeeded(
            "fake_pay_{$payment->id}",
            $payment->amount,
            'usd',
            $isCard ? 'visa' : null,
            $isCard ? '4242' : null,
        );
    }

    public function lookup(Payment $payment): PaymentOutcome
    {
        $this->lookedUp[] = $payment->id;

        $next = array_shift($this->lookupOutcomes) ?? new Cancelled;

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function cancel(Payment $payment): PaymentOutcome
    {
        $this->cancelled[] = $payment->id;

        $outcome = $this->cancelOutcome ?? new Cancelled;
        $this->cancelOutcome = null;

        return $outcome;
    }
}
