<?php

use App\Actions\ClosePayment;
use App\Actions\StartOrderPayment;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Mail\OrderReceiptMail;
use App\Mail\PaymentProblemMail;
use App\Mail\TopUpReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\StillPending;
use App\Payments\PaymentGateways;
use App\Wallet\Wallet;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
});

function pendingPaymentCreated(DateTimeInterface $at, array $state = []): Payment
{
    $order = Order::factory()->awaitingPayment()->create();

    return Payment::factory()->forOrder($order)->withReference('cs_'.Str::random(8))->create(['created_at' => $at] + $state);
}

/**
 * A pending Stripe order payment for the checkoutSetup() basket, started four hours ago.
 *
 * @return array{0: User, 1: Payment}
 */
function staleOrderPayment(): array
{
    [$user, $payment] = startedOrderPayment();
    $payment->forceFill(['created_at' => now()->subHours(4)])->save();

    return [$user, $payment];
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

it('places the order when the provider says a stale payment was paid', function () {
    [$user, $payment] = staleOrderPayment();
    $this->fake->willLookUp($this->fake->paid($payment));

    $this->artisan('payments:expire-stale')
        ->expectsOutput('Expired 0 payments.')
        ->assertSuccessful();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->card_last4)->toBe('4242')
        ->and($payment->order->status)->toBe(OrderStatus::Placed)
        ->and(Cart::forUser($user)->count())->toBe(0)
        ->and($this->fake->lookedUp)->toBe([$payment->id])
        ->and($this->fake->confirmed)->toBeEmpty();

    Mail::assertQueued(OrderReceiptMail::class, 1);
    Mail::assertNotQueued(PaymentProblemMail::class);
});

it('credits a stale top-up the provider says was paid', function () {
    $payment = Payment::factory()->topUp(2500)->withReference('cs_top')->create(['created_at' => now()->subHours(4)]);
    $this->fake->willLookUp($this->fake->paid($payment));

    $this->artisan('payments:expire-stale')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and(WalletTransaction::query()->where('payment_id', $payment->id)->sole()->type)->toBe(WalletTransactionType::TopUp);

    Mail::assertQueued(TopUpReceiptMail::class, 1);
    Mail::assertNotQueued(PaymentProblemMail::class);
});

it('expires a stale payment the provider says was not paid, with the email', function () {
    [, $payment] = staleOrderPayment();

    $this->artisan('payments:expire-stale')
        ->expectsOutput('Expired 1 payments.')
        ->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->fake->lookedUp)->toBe([$payment->id]);

    Mail::assertQueued(PaymentProblemMail::class, 1);
    Mail::assertNotQueued(OrderReceiptMail::class);
});

it('keeps a stale payment whose capture PayPal is holding', function () {
    $payment = pendingPaymentCreated(now()->subHours(4), ['method' => 'paypal']);
    $this->fake->willLookUp(new StillPending('CAP9'));

    $this->artisan('payments:expire-stale')->expectsOutput('Expired 0 payments.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_payment_id)->toBe('CAP9');

    Mail::assertNothingQueued();
});

it('leaves a payment for the next run when the provider cannot be reached, and goes on', function () {
    $unreachable = pendingPaymentCreated(now()->subHours(4));
    $stale = pendingPaymentCreated(now()->subHours(4));
    $this->fake->willLookUp(new PaymentProviderUnavailable('Stripe could not be reached for payment 1.'));

    $this->artisan('payments:expire-stale')
        ->expectsOutput('Expired 1 payments.')
        ->expectsOutput('1 payments could not be processed.')
        ->assertFailed();

    expect($unreachable->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($unreachable->order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Expired);

    Mail::assertQueued(PaymentProblemMail::class, 1);
    Mail::assertQueued(PaymentProblemMail::class, fn (PaymentProblemMail $mail) => $mail->payment->is($stale));
});

it('reports any other failure and goes on', function () {
    Exceptions::fake();
    $failing = pendingPaymentCreated(now()->subHours(4));
    $stale = pendingPaymentCreated(now()->subHours(4));
    $this->fake->willLookUp(new RuntimeException('Lock wait timeout exceeded'));

    $this->artisan('payments:expire-stale')
        ->expectsOutput('Expired 1 payments.')
        ->assertFailed();

    expect($failing->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Expired);

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Lock wait timeout exceeded');
});

it('is scheduled every fifteen minutes without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'payments:expire-stale'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('expires a stale split payment the provider no longer knows and returns the share', function (PaymentMethod $method) {
    [$user, $ups] = checkoutSetup(500);
    app(StartOrderPayment::class)->handle($user, $ups, $method, useWallet: true);
    $payment = Payment::query()->latest('id')->firstOrFail();
    $payment->forceFill(['created_at' => now()->subHours(4)])->save();
    expect($user->fresh()->balance->cents())->toBe(0);

    // The real gateways answer, with the provider faked at the HTTP level.
    app()->forgetInstance(PaymentGateways::class);
    config(['services.stripe.secret' => 'sk_test_secret', 'services.paypal.client_id' => 'client-id', 'services.paypal.secret' => 'client-secret']);
    $stripe = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($stripe);
    Http::preventStrayRequests();

    if ($method === PaymentMethod::Stripe) {
        $stripe->queue(404, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such checkout.session']]);
    } else {
        Cache::flush();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/*' => Http::response(['name' => 'RESOURCE_NOT_FOUND', 'details' => [['issue' => 'INVALID_RESOURCE_ID']]], 404),
        ]);
    }

    try {
        $this->artisan('payments:expire-stale')
            ->expectsOutput('Expired 1 payments.')
            ->assertSuccessful();
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($payment->order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($user->fresh()->balance->cents())->toBe(500)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Release)->count())->toBe(1);

    Mail::assertQueued(PaymentProblemMail::class, 1);
})->with([PaymentMethod::Stripe, PaymentMethod::PayPal]);
