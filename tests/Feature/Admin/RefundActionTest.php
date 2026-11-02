<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\RefundRefused;
use App\Payments\PaymentGateways;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->fake = PaymentGateways::fake();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * A placed $122.74 order: $100.00 from the wallet and $22.74 through the provider.
 */
function splitPanelOrder(PaymentMethod $method = PaymentMethod::Stripe, ?string $brand = 'visa', ?string $last4 = '4242'): Order
{
    $order = Order::factory()->create([
        'subtotal' => 12274, 'shipping_fee' => 0, 'total' => 12274,
        'payment_method' => $method, 'wallet_amount' => 10000,
    ]);

    Payment::factory()->forOrder($order)->succeeded()->create([
        'method' => $method, 'provider_payment_id' => 'pi_1', 'card_brand' => $brand, 'card_last4' => $last4,
    ]);

    return $order;
}

function openOrder(Order $order): Testable
{
    return Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
}

/**
 * The text of the open confirmation: its submit button, then its body.
 * (Filament renders action modals as a separate Livewire partial, so assertSee can't reach them.)
 */
function modalText(Testable $component): string
{
    $page = $component->instance();
    $schema = (fn () => $this->getMountedActionSchema())->call($page);

    return $page->getMountedAction()->getModalSubmitActionLabel().' | '.trim(preg_replace('/\s+/', ' ', strip_tags($schema->toHtml())));
}

it('lists the breakdown, asks for a reason and puts the total on the button', function () {
    $order = splitPanelOrder();

    $component = openOrder($order)->mountAction('refundOrder');

    expect(modalText($component))->toBe('Refund $122.74 | Visa ending in 4242, through Stripe: $22.74 Wallet: $100.00 Total: $122.74 Reason*');

    $component->callMountedAction()->assertHasActionErrors(['reason' => 'required']);

    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('rejects a reason longer than 255 characters', function () {
    $order = splitPanelOrder();

    openOrder($order)
        ->callAction('refundOrder', ['reason' => str_repeat('a', 256)])
        ->assertHasActionErrors(['reason' => 'max']);
});

it('words the provider line by method and card', function (PaymentMethod $method, ?string $brand, ?string $last4, string $line) {
    $order = splitPanelOrder($method, $brand, $last4);

    expect(modalText(openOrder($order)->mountAction('refundOrder')))->toContain("{$line} Wallet: \$100.00 Total: \$122.74");
})->with([
    'Stripe with a card' => [PaymentMethod::Stripe, 'mastercard', '4444', 'Mastercard ending in 4444, through Stripe: $22.74'],
    'Stripe without a card' => [PaymentMethod::Stripe, null, null, 'Stripe: $22.74'],
    'PayPal' => [PaymentMethod::PayPal, null, null, 'PayPal: $22.74'],
]);

it('shows only the wallet line and the total for a wallet-only order', function () {
    $order = Order::factory()->create(['subtotal' => 5000, 'shipping_fee' => 0, 'total' => 5000, 'wallet_amount' => 5000]);

    expect(modalText(openOrder($order)->mountAction('refundOrder')))->toBe('Refund $50.00 | Wallet: $50.00 Total: $50.00 Reason*');
});

it('refunds the order and says so', function () {
    $order = splitPanelOrder();

    openOrder($order)
        ->callAction('refundOrder', ['reason' => 'Damaged in transit'])
        ->assertNotified(Notification::make()->success()->title("Order #{$order->id} is refunded."));

    $refund = Refund::query()->where('order_id', $order->id)->sole();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($refund->reason)->toBe('Damaged in transit')
        ->and($refund->admin_id)->toBe($this->admin->id)
        ->and($refund->status)->toBe(RefundStatus::Succeeded);
});

it('shows the refusal as a danger notification', function () {
    $order = splitPanelOrder();
    $this->fake->willRefund(new RefundRefused('The charge is too old'));

    openOrder($order)
        ->callAction('refundOrder', ['reason' => 'Changed mind'])
        ->assertNotified(Notification::make()->danger()->title('Stripe refused the refund: The charge is too old.'));

    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('says the provider could not be reached', function (PaymentMethod $method, string $name) {
    $order = splitPanelOrder($method);
    $this->fake->willRefund(new PaymentProviderUnavailable('timeout'));

    openOrder($order)
        ->callAction('refundOrder', ['reason' => 'Changed mind'])
        ->assertNotified(Notification::make()->danger()->title("{$name} couldn't be reached. Try again in a moment."));

    expect($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and($order->refund->status)->toBe(RefundStatus::Pending);
})->with([
    'Stripe' => [PaymentMethod::Stripe, 'Stripe'],
    'PayPal' => [PaymentMethod::PayPal, 'PayPal'],
]);

it('swaps Refund order for Retry refund while the refund is pending or failed', function (RefundStatus $status) {
    $order = splitPanelOrder();
    Refund::factory()->for($order)->create([
        'status' => $status, 'payment_id' => $order->succeededPayment->id,
        'provider_amount' => 2274, 'wallet_amount' => 10000,
    ]);

    openOrder($order)
        ->assertActionHidden('refundOrder')
        ->assertActionVisible('retryRefund');
})->with([RefundStatus::Pending, RefundStatus::Failed]);

it('retries a failed refund with a fresh reason', function () {
    $order = splitPanelOrder();
    Refund::factory()->for($order)->create([
        'status' => RefundStatus::Failed, 'failure_reason' => 'Declined', 'payment_id' => $order->succeededPayment->id,
        'provider_amount' => 2274, 'wallet_amount' => 10000,
    ]);

    openOrder($order)
        ->callAction('retryRefund', ['reason' => 'Trying again'])
        ->assertNotified(Notification::make()->success()->title("Order #{$order->id} is refunded."));

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->refund->reason)->toBe('Trying again');
});

it('offers neither action for a succeeded refund or an order that is not placed', function () {
    $refunded = splitPanelOrder();
    Refund::factory()->for($refunded)->create(['status' => RefundStatus::Succeeded]);
    $refunded->update(['status' => OrderStatus::Refunded]);
    $cancelled = Order::factory()->cancelled()->create();
    $awaiting = Order::factory()->awaitingPayment()->create();

    foreach ([$refunded, $cancelled, $awaiting] as $order) {
        openOrder($order)->assertActionHidden('refundOrder')->assertActionHidden('retryRefund');
    }
});

it('shows a refund whose admin was deleted as by a deleted admin', function () {
    $order = splitPanelOrder();
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    Refund::factory()->for($order)->create([
        'admin_id' => $admin->id, 'status' => RefundStatus::Succeeded, 'completed_at' => now(),
    ]);

    $admin->delete();

    openOrder($order)
        ->assertSuccessful()
        ->assertSee('Deleted admin')
        ->assertDontSee('Ada Admin');
});

it('shows a Refund section only when there is a refund', function () {
    $order = splitPanelOrder();

    openOrder($order)->assertDontSee('Provider refund');

    $refund = Refund::factory()->for($order)->create([
        'admin_id' => User::factory()->admin()->create(['name' => 'Ada Admin'])->id,
        'status' => RefundStatus::Succeeded,
        'reason' => 'Damaged in transit',
        'provider_amount' => 2274, 'wallet_amount' => 10000,
        'provider_refund_id' => 're_123',
        'completed_at' => now(),
    ]);

    openOrder($order)
        ->assertSee('Provider refund')
        ->assertSee('Ada Admin')
        ->assertSee('Damaged in transit')
        ->assertSee('$100.00')
        ->assertSee('$22.74')
        ->assertSee('Succeeded')
        ->assertSee('re_123')
        ->assertDontSee('Failure reason');

    $refund->update(['status' => RefundStatus::Failed, 'failure_reason' => 'Card closed']);

    openOrder($order)->assertSee('Failed')->assertSee('Failure reason')->assertSee('Card closed');
});
