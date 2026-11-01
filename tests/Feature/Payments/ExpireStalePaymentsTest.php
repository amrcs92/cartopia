<?php

use App\Actions\ClosePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Wallet\Wallet;
use Illuminate\Console\Scheduling\Schedule;

function pendingPaymentCreated(DateTimeInterface $at, array $state = []): Payment
{
    $order = Order::factory()->awaitingPayment()->create();

    return Payment::factory()->forOrder($order)->create(['created_at' => $at] + $state);
}

it('expires only old pending payments', function () {
    $this->travelTo(now()->startOfMinute());

    $stale = pendingPaymentCreated(now()->subHours(3)->subMinute());
    $recent = pendingPaymentCreated(now()->subHours(3)->addMinute());
    $settled = pendingPaymentCreated(now()->subHours(4), ['status' => PaymentStatus::Succeeded, 'completed_at' => now()]);
    $held = pendingPaymentCreated(now()->subHours(4), ['provider_payment_id' => 'CAP1']);

    $this->artisan('payments:expire-stale')
        ->expectsOutput('Expired 1 payments.')
        ->assertSuccessful();

    expect($stale->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($stale->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($recent->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($settled->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($held->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('does not count a payment that settles before it is closed', function () {
    $this->travelTo(now()->startOfMinute());

    $racing = pendingPaymentCreated(now()->subHours(5));
    $stale = pendingPaymentCreated(now()->subHours(5));

    // Settles the first payment just before the real close runs.
    $this->app->bind(ClosePayment::class, fn () => new class(app(Wallet::class)) extends ClosePayment
    {
        public function handle(Payment $payment, PaymentStatus $status, bool $notify): Payment
        {
            if ($payment->id === Payment::query()->min('id')) {
                Payment::query()->whereKey($payment->id)->update(['status' => PaymentStatus::Succeeded]);
            }

            return parent::handle($payment, $status, $notify);
        }
    });

    $this->artisan('payments:expire-stale')->expectsOutput('Expired 1 payments.');

    expect($racing->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('is scheduled every fifteen minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'payments:expire-stale'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
