<?php

use App\Actions\Admin\RefundOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\AdminActionRefused;
use App\Mail\OrderRefundedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\FakePaymentGateway;
use App\Payments\Outcomes\RefundAccepted;
use App\Payments\Outcomes\RefundOutcome;
use App\Payments\Outcomes\RefundRefused;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
    $this->admin = User::factory()->admin()->create();
});

/**
 * A placed $122.74 order: all wallet, or $22.74 through the provider plus a
 * wallet part, with the provider's succeeded payment.
 */
function refundableOrder(PaymentMethod $method, int $walletCents = 0): Order
{
    $total = $method === PaymentMethod::Wallet ? 12274 : 2274 + $walletCents;

    $order = Order::factory()->create([
        'subtotal' => $total,
        'shipping_fee' => 0,
        'total' => $total,
        'payment_method' => $method,
        'wallet_amount' => $method === PaymentMethod::Wallet ? $total : $walletCents,
    ]);

    if ($method !== PaymentMethod::Wallet) {
        Payment::factory()->forOrder($order)->succeeded()->create([
            'method' => $method,
            'provider_payment_id' => 'pi_or_capture_1',
        ]);
    }

    return $order;
}

/**
 * @return list<string>
 */
function refundKeysSeen(FakePaymentGateway $fake): array
{
    return array_map(fn (array $call): string => $call[1]->idempotencyKey(), $fake->refunded);
}

dataset('refund providers', [
    'Stripe' => [PaymentMethod::Stripe, 'Stripe'],
    'PayPal' => [PaymentMethod::PayPal, 'PayPal'],
]);

it('refunds a wallet-only order to the wallet without calling a gateway', function () {
    $order = refundableOrder(PaymentMethod::Wallet);

    $refund = app(RefundOrder::class)->handle($order, $this->admin, '  Damaged in transit  ');

    $row = WalletTransaction::query()->where('refund_id', $refund->id)->sole();

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->reason)->toBe('Damaged in transit')
        ->and($refund->admin_id)->toBe($this->admin->id)
        ->and($refund->payment_id)->toBeNull()
        ->and($refund->wallet_amount->cents())->toBe(12274)
        ->and($refund->provider_amount->cents())->toBe(0)
        ->and($refund->provider_refund_id)->toBeNull()
        ->and($refund->completed_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->user->fresh()->balance->cents())->toBe(12274)
        ->and($row->type)->toBe(WalletTransactionType::Refund)
        ->and($row->amount->cents())->toBe(12274)
        ->and($row->description)->toBe("Refund for order #{$order->id}")
        ->and($row->order_id)->toBe($order->id)
        ->and($this->fake->refunded)->toBe([]);
    Mail::assertQueued(OrderRefundedMail::class, 1);
    Mail::assertQueued(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => $mail->hasTo($order->user->email) && $mail->order->is($order));
});

it('refunds a provider-only order through the gateway and writes no wallet row', function (PaymentMethod $method) {
    $order = refundableOrder($method);
    $payment = $order->succeededPayment;
    $this->fake->willRefund(new RefundAccepted('re_123'));

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Customer changed their mind');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('re_123')
        ->and($refund->payment_id)->toBe($payment->id)
        ->and($refund->provider_amount->cents())->toBe(2274)
        ->and($refund->wallet_amount->cents())->toBe(0)
        ->and($refund->completed_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(WalletTransaction::query()->where('order_id', $order->id)->exists())->toBeFalse()
        ->and($order->user->fresh()->balance->cents())->toBe(0)
        ->and($this->fake->refunded)->toHaveCount(1)
        ->and($this->fake->refunded[0][0]->id)->toBe($payment->id)
        ->and(refundKeysSeen($this->fake))->toBe(["refund-{$refund->id}-{$refund->created_at->getTimestamp()}-1"]);
    Mail::assertQueued(OrderRefundedMail::class, 1);
    Mail::assertQueued(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => $mail->hasTo($order->user->email) && $mail->order->is($order));
})->with('refund providers');

it('refunds both parts of a split order', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock');

    $row = WalletTransaction::query()->where('refund_id', $refund->id)->sole();

    expect($order->isSplit())->toBeTrue()
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_amount->cents())->toBe(2274)
        ->and($refund->wallet_amount->cents())->toBe(10000)
        ->and($refund->provider_refund_id)->toBe("re_fake_{$refund->id}")
        ->and($row->type)->toBe(WalletTransactionType::Refund)
        ->and($row->amount->cents())->toBe(10000)
        ->and($row->description)->toBe("Refund for order #{$order->id}")
        ->and($order->user->fresh()->balance->cents())->toBe(10000)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($this->fake->refunded)->toHaveCount(1);
    Mail::assertQueued(OrderRefundedMail::class, 1);
    Mail::assertQueued(OrderRefundedMail::class, fn (OrderRefundedMail $mail) => $mail->hasTo($order->user->email));
})->with('refund providers');

it('marks the refund failed when the provider refuses, and credits nothing', function (PaymentMethod $method, string $name) {
    $order = refundableOrder($method, walletCents: 10000);
    $this->fake->willRefund(new RefundRefused('Charge already refunded'));

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(AdminActionRefused::class, "{$name} refused the refund: Charge already refunded.");

    $refund = $order->refund()->sole();

    expect($refund->status)->toBe(RefundStatus::Failed)
        ->and($refund->failure_reason)->toBe('Charge already refunded')
        ->and($refund->completed_at)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(WalletTransaction::query()->where('order_id', $order->id)->exists())->toBeFalse()
        ->and($order->user->fresh()->balance->cents())->toBe(0);
    Mail::assertNothingQueued();
})->with('refund providers');

it('retries a refused refund with the next attempt and a new key', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);
    $first = User::factory()->admin()->create();
    $this->fake->willRefund(new RefundRefused('Try later'), new RefundAccepted('re_2'));

    expect(fn () => app(RefundOrder::class)->handle($order, $first, 'First try'))
        ->toThrow(AdminActionRefused::class);

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Second try');
    $stamp = $refund->created_at->getTimestamp();

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->attempt)->toBe(2)
        ->and($refund->admin_id)->toBe($this->admin->id)
        ->and($refund->reason)->toBe('Second try')
        ->and($refund->failure_reason)->toBeNull()
        ->and($refund->provider_refund_id)->toBe('re_2')
        ->and(Refund::query()->count())->toBe(1)
        ->and(refundKeysSeen($this->fake))->toBe([
            "refund-{$refund->id}-{$stamp}-1",
            "refund-{$refund->id}-{$stamp}-2",
        ])
        ->and($order->user->fresh()->balance->cents())->toBe(10000);
})->with('refund providers');

it('leaves the refund pending when the provider cannot be reached', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);
    $this->fake->willRefund(new PaymentProviderUnavailable('Timed out'));

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(PaymentProviderUnavailable::class);

    $refund = $order->refund()->sole();

    expect($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->attempt)->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(WalletTransaction::query()->where('order_id', $order->id)->exists())->toBeFalse();
    Mail::assertNothingQueued();
})->with('refund providers');

it('retries an unreachable refund with the same attempt and key', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);
    $first = User::factory()->admin()->create();
    $this->fake->willRefund(new PaymentProviderUnavailable('Timed out'), new RefundAccepted('re_9'));

    expect(fn () => app(RefundOrder::class)->handle($order, $first, 'Original reason'))
        ->toThrow(PaymentProviderUnavailable::class);

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Retry reason');
    $key = "refund-{$refund->id}-{$refund->created_at->getTimestamp()}-1";

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->attempt)->toBe(1)
        ->and($refund->admin_id)->toBe($first->id)
        ->and($refund->reason)->toBe('Original reason')
        ->and($refund->provider_refund_id)->toBe('re_9')
        ->and(refundKeysSeen($this->fake))->toBe([$key, $key])
        ->and($order->user->fresh()->balance->cents())->toBe(10000)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
})->with('refund providers');

it('treats rejected credentials as unreachable and resumes with the same key', function (PaymentMethod $method, string $name) {
    $order = refundableOrder($method);
    $this->fake->willRefund(new PaymentProviderRejected('401'), new RefundAccepted('re_5'));

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(PaymentProviderUnavailable::class, "{$name} couldn't be reached. Try again in a moment.");

    expect($order->refund()->sole()->status)->toBe(RefundStatus::Pending);

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock');
    $key = "refund-{$refund->id}-{$refund->created_at->getTimestamp()}-1";

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->attempt)->toBe(1)
        ->and(refundKeysSeen($this->fake))->toBe([$key, $key]);
})->with('refund providers');

it('refuses a second refund after success and calls the gateway once', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);

    app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock');

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Again'))
        ->toThrow(AdminActionRefused::class, 'This order is already refunded.');

    expect($this->fake->refunded)->toHaveCount(1)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Refund)->count())->toBe(1)
        ->and($order->user->fresh()->balance->cents())->toBe(10000);
    Mail::assertQueued(OrderRefundedMail::class, 1);
})->with('refund providers');

it('credits once when two clicks both reach the finish step', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);

    // While the first click waits on the provider, a second click runs the whole refund.
    $gateway = new class extends FakePaymentGateway
    {
        public ?Closure $during = null;

        public function refund(Payment $payment, Refund $refund): RefundOutcome
        {
            $during = $this->during;
            $this->during = null;

            $outcome = parent::refund($payment, $refund);

            if ($during !== null) {
                $during();
            }

            return $outcome;
        }
    };
    app()->instance(PaymentGateways::class, new PaymentGateways([
        PaymentMethod::Stripe->value => fn (): PaymentGateway => $gateway,
        PaymentMethod::PayPal->value => fn (): PaymentGateway => $gateway,
    ], everythingAvailable: true));

    $second = null;
    $gateway->during = function () use ($order, &$second) {
        $second = app(RefundOrder::class)->handle(Order::find($order->id), $this->admin, 'Second click');
    };

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'First click');
    $key = "refund-{$refund->id}-{$refund->created_at->getTimestamp()}-1";

    expect($second?->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->id)->toBe($second->id)
        ->and(refundKeysSeen($gateway))->toBe([$key, $key])
        ->and(WalletTransaction::query()->where('refund_id', $refund->id)->count())->toBe(1)
        ->and($order->user->fresh()->balance->cents())->toBe(10000)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
    Mail::assertQueued(OrderRefundedMail::class, 1);
})->with('refund providers');

it('refuses orders that are not placed', function (string $state) {
    $order = Order::factory()->{$state}()->create();

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(AdminActionRefused::class, 'Only placed orders can be refunded.');

    expect(Refund::query()->count())->toBe(0)
        ->and($this->fake->refunded)->toBe([]);
})->with(['awaitingPayment', 'cancelled']);

it('refuses an actor who is no longer an admin, read fresh', function () {
    $order = refundableOrder(PaymentMethod::Stripe);

    // Demoted elsewhere; this model still says admin.
    User::query()->whereKey($this->admin->id)->update(['is_admin' => false]);

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(AdminActionRefused::class, 'Only admins can refund orders.');

    expect(Refund::query()->count())->toBe(0)
        ->and($this->fake->refunded)->toBe([])
        ->and($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('requires a reason', function (string $reason) {
    $order = refundableOrder(PaymentMethod::Wallet);

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, $reason))
        ->toThrow(InvalidArgumentException::class);

    expect(Refund::query()->count())->toBe(0);
})->with(['empty' => '', 'blank' => "   \n "]);

it('rejects a reason longer than the column holds', function () {
    $order = refundableOrder(PaymentMethod::Wallet);

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, str_repeat('a', RefundOrder::MAX_REASON_BYTES + 1)))
        ->toThrow(InvalidArgumentException::class);

    expect(Refund::query()->count())->toBe(0);
});

it('finishes an accepted refund on retry without asking the provider again', function (PaymentMethod $method) {
    $order = refundableOrder($method, walletCents: 10000);
    // Were the provider asked again after its key expired, it would refuse.
    $this->fake->willRefund(new RefundAccepted('re_kept'), new RefundRefused('Charge already refunded'));

    // The process dies after the provider accepted, before the finish commits.
    app()->instance(Wallet::class, new class extends Wallet
    {
        private bool $crashed = false;

        public function credit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null, ?Order $order = null, ?Payment $payment = null, ?User $admin = null, ?Refund $refund = null): WalletTransaction
        {
            if (! $this->crashed) {
                $this->crashed = true;

                throw new RuntimeException('The process died.');
            }

            return parent::credit($user, $amount, $type, $description, $order, $payment, $admin, $refund);
        }
    });

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(RuntimeException::class, 'The process died.');

    $pending = $order->refund()->sole();

    expect($pending->status)->toBe(RefundStatus::Pending)
        ->and($pending->provider_refund_id)->toBe('re_kept')
        ->and($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and(WalletTransaction::query()->where('order_id', $order->id)->exists())->toBeFalse();

    $refund = app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock');

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('re_kept')
        ->and($refund->attempt)->toBe(1)
        ->and($this->fake->refunded)->toHaveCount(1)
        ->and(WalletTransaction::query()->where('refund_id', $refund->id)->count())->toBe(1)
        ->and($order->user->fresh()->balance->cents())->toBe(10000)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
})->with('refund providers');

it('says no reason was given when the provider refuses without one', function (PaymentMethod $method, string $name) {
    $order = refundableOrder($method);
    $this->fake->willRefund(new RefundRefused(''));

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(AdminActionRefused::class, "{$name} refused the refund: no reason given.");

    expect($order->refund()->sole()->status)->toBe(RefundStatus::Failed);
})->with('refund providers');

it('passes on a refusal another click got while this one waited', function (PaymentMethod $method, string $name) {
    $order = refundableOrder($method);
    $refund = Refund::factory()->create([
        'order_id' => $order->id,
        'payment_id' => $order->succeededPayment->id,
        'provider_amount' => 2274,
        'wallet_amount' => 0,
    ]);

    // The other click is refused between this click's open and its provider step.
    $refusedMeanwhile = false;
    Payment::retrieved(function () use (&$refusedMeanwhile, $refund) {
        if ($refusedMeanwhile) {
            return;
        }

        $refusedMeanwhile = true;
        Refund::query()->whereKey($refund->id)->update(['status' => RefundStatus::Failed->value, 'failure_reason' => '']);
    });

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(AdminActionRefused::class, "{$name} refused the refund: no reason given.");

    expect($refusedMeanwhile)->toBeTrue()
        ->and($this->fake->refunded)->toBe([])
        ->and($refund->fresh()->status)->toBe(RefundStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Placed);
})->with('refund providers');

it('refuses to open a refund without a succeeded payment for the provider part', function (string $case) {
    $order = refundableOrder(PaymentMethod::Stripe, walletCents: 10000);
    $payment = $order->succeededPayment;

    match ($case) {
        'no succeeded payment' => $payment->update(['status' => PaymentStatus::Failed]),
        'a different amount' => $payment->update(['amount' => 2000]),
    };

    expect(fn () => app(RefundOrder::class)->handle($order, $this->admin, 'Out of stock'))
        ->toThrow(LogicException::class, "Order {$order->id} has no succeeded payment matching its provider part.");

    expect(Refund::query()->count())->toBe(0)
        ->and($this->fake->refunded)->toBe([]);
})->with(['no succeeded payment', 'a different amount']);
