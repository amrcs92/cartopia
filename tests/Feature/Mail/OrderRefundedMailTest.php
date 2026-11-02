<?php

use App\Actions\Admin\RefundOrder;
use App\Enums\PaymentMethod;
use App\Mail\OrderRefundedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\PaymentGateways;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    PaymentGateways::fake();
});

/**
 * A placed $122.74 order of two items with $5.00 delivery, refunded in full by an admin.
 * $walletCents is the wallet part; the rest goes through $method's payment.
 */
function refundedMailOrder(PaymentMethod $method, int $walletCents = 0, ?string $brand = 'visa', ?string $last4 = '4242'): Order
{
    $user = User::factory()->create(['balance' => 3500]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'shipping_method_id' => ShippingMethod::factory()->create(['name' => 'Delivery']),
        'subtotal' => 11774,
        'shipping_fee' => 500,
        'total' => 12274,
        'payment_method' => $method,
        'wallet_amount' => $method === PaymentMethod::Wallet ? 12274 : $walletCents,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Oat & Honey Granola', 'unit_price' => 1500, 'quantity' => 2, 'line_total' => 3000]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Olive Oil', 'unit_price' => 8774, 'quantity' => 1, 'line_total' => 8774]);

    if ($method !== PaymentMethod::Wallet) {
        Payment::factory()->forOrder($order)->succeeded()->create([
            'user_id' => $user->id,
            'method' => $method,
            'provider_payment_id' => 'pi_or_capture_1',
            'card_brand' => $method === PaymentMethod::Stripe ? $brand : null,
            'card_last4' => $method === PaymentMethod::Stripe ? $last4 : null,
        ]);
    }

    app(RefundOrder::class)->handle($order, User::factory()->admin()->create(), 'Damaged in transit');
    Mail::fake();

    return $order->fresh();
}

it('renders the refund receipt', function () {
    $order = refundedMailOrder(PaymentMethod::Stripe, walletCents: 10000);
    $mail = new OrderRefundedMail($order);
    $html = $mail->render();
    $text = $mail->renderText();
    $date = $order->refund->completed_at->format('j M Y, H:i');

    expect($mail->envelope()->subject)->toBe("Your Cartopia Order #{$order->id} Has Been Refunded")
        ->and($html)->toContain('Your order has been refunded')
        ->and($html)->toContain("We've refunded your order in full. Here's where the money went.")
        ->and($html)->toContain('font-weight:700;line-height:36px;">↩</td>')
        ->and($html)->not->toContain('&amp;#')
        ->and($html)->toContain("Order #{$order->id} &middot; Delivery")
        ->and($html)->toContain("Refunded {$date}")
        ->and($html)->toContain('Oat &amp; Honey Granola')
        ->and($html)->toContain('$30.00')
        ->and($html)->toContain('$117.74')
        ->and($html)->toContain('$5.00')
        ->and($html)->toContain('$122.74')
        ->and($html)->toContain('View your order')
        ->and($html)->toContain(route('orders.show', $order))
        ->and($text)->toContain('Your order has been refunded')
        ->and($text)->toContain("We've refunded your order in full. Here's where the money went.")
        ->and($text)->toContain("Order #{$order->id} · Delivery — Refunded {$date}")
        ->and($text)->toContain('Oat & Honey Granola × 2  $30.00')
        ->and($text)->toContain('Subtotal: $117.74')
        ->and($text)->toContain('Delivery: $5.00')
        ->and($text)->toContain('Total: $122.74')
        ->and($text)->toContain('View your order: '.route('orders.show', $order))
        ->and($text)->not->toContain('&amp;');
});

it('shows where the money went for each payment shape', function (PaymentMethod $method, int $wallet, ?string $brand, ?string $last4, array $expected, bool $note) {
    $order = refundedMailOrder($method, $wallet, $brand, $last4);
    $mail = new OrderRefundedMail($order);
    $html = $mail->render();
    $text = $mail->renderText();

    foreach ($expected as $label => $value) {
        expect($html)->toContain($label)->toContain($value)
            ->and($text)->toContain("{$label}: {$value}");
    }
    expect($html)->not->toContain('&amp;#');

    $noteText = 'Card and PayPal refunds can take 5–10 business days to show.';
    if ($note) {
        expect($html)->toContain($noteText)->and($text)->toContain($noteText);
    } else {
        expect($html)->not->toContain('business days')->and($text)->not->toContain('business days');
    }
})->with([
    'wallet only' => [PaymentMethod::Wallet, 0, null, null, [
        'Refunded to your wallet' => '$122.74', 'Wallet balance after' => '$157.74',
    ], false],
    'Stripe with a brand' => [PaymentMethod::Stripe, 0, 'visa', '4242', [
        'Refunded to Visa ending in 4242' => '$122.74',
    ], true],
    'Stripe without a brand' => [PaymentMethod::Stripe, 0, null, '4242', [
        'Refunded to Card ending in 4242' => '$122.74',
    ], true],
    'Stripe without card details' => [PaymentMethod::Stripe, 0, null, null, [
        'Refunded to Stripe' => '$122.74',
    ], true],
    'PayPal' => [PaymentMethod::PayPal, 0, null, null, [
        'Refunded to PayPal' => '$122.74',
    ], true],
    'split' => [PaymentMethod::Stripe, 10000, 'visa', '4242', [
        'Refunded to Visa ending in 4242' => '$22.74', 'Refunded to your wallet' => '$100.00', 'Wallet balance after' => '$135.00',
    ], true],
]);

it('leaves out the wallet rows when the refund has no wallet part', function () {
    $order = refundedMailOrder(PaymentMethod::PayPal);
    $mail = new OrderRefundedMail($order);

    expect($mail->render())->not->toContain('Refunded to your wallet')->not->toContain('Wallet balance after')
        ->and($mail->renderText())->not->toContain('wallet');
});

it('leaves out the provider note and row when the refund is wallet only', function () {
    $order = refundedMailOrder(PaymentMethod::Wallet);
    $html = (new OrderRefundedMail($order))->render();

    expect($html)->not->toContain('Visa')->not->toContain('PayPal')->not->toContain('Stripe');
});

it('is queued after commit', function () {
    $mail = new OrderRefundedMail(refundedMailOrder(PaymentMethod::Wallet));

    expect($mail)->toBeInstanceOf(ShouldQueue::class)
        ->and($mail->afterCommit)->toBeTrue();
});
