<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists orders with the number, customer, items, total, paid with and status', function () {
    $shopper = User::factory()->create(['name' => 'Sam Shopper']);
    $order = Order::factory()->for($shopper)->create(['subtotal' => 1500, 'shipping_fee' => 0, 'total' => 1500, 'wallet_amount' => 1500]);
    OrderItem::factory()->for($order)->create(['quantity' => 2]);
    OrderItem::factory()->for($order)->create(['quantity' => 3]);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->assertTableColumnStateSet('id', $order->id, $order)
        ->assertTableColumnStateSet('user.name', 'Sam Shopper', $order)
        ->assertTableColumnStateSet('items_sum_quantity', 5, $order)
        ->assertTableColumnStateSet('total', '$15.00', $order)
        ->assertTableColumnStateSet('paid_with', 'Wallet', $order)
        ->assertTableColumnStateSet('status', 'Placed', $order)
        ->assertTableColumnExists('created_at');
});

it('names how each order was paid as the filter does', function (PaymentMethod $method, int $walletCents, string $paidWith) {
    $order = Order::factory()->create([
        'payment_method' => $method, 'total' => 2000, 'subtotal' => 2000, 'shipping_fee' => 0, 'wallet_amount' => $walletCents,
    ]);

    Livewire::test(ListOrders::class)
        ->assertTableColumnStateSet('paid_with', $paidWith, $order);
})->with([
    'wallet' => [PaymentMethod::Wallet, 2000, 'Wallet'],
    'Stripe' => [PaymentMethod::Stripe, 0, 'Stripe'],
    'PayPal' => [PaymentMethod::PayPal, 0, 'PayPal'],
    'wallet and Stripe' => [PaymentMethod::Stripe, 500, 'Wallet + Stripe'],
    'wallet and PayPal' => [PaymentMethod::PayPal, 500, 'Wallet + PayPal'],
]);

it('lists newest first', function () {
    $older = Order::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Order::factory()->create(['created_at' => now()]);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('searches by order number, customer name and email', function () {
    $sam = User::factory()->create(['name' => 'Sam Shopper', 'email' => 'sam@example.com']);
    $kim = User::factory()->create(['name' => 'Kim Buyer', 'email' => 'kim@example.com']);
    $samOrder = Order::factory()->for($sam)->create();
    $kimOrder = Order::factory()->for($kim)->create();

    Livewire::test(ListOrders::class)
        ->searchTable('Sam')
        ->assertCanSeeTableRecords([$samOrder])
        ->assertCanNotSeeTableRecords([$kimOrder])
        ->searchTable('kim@example')
        ->assertCanSeeTableRecords([$kimOrder])
        ->assertCanNotSeeTableRecords([$samOrder])
        ->searchTable((string) $samOrder->id)
        ->assertCanSeeTableRecords([$samOrder])
        ->assertCanNotSeeTableRecords([$kimOrder]);
});

it('filters by status', function () {
    $placed = Order::factory()->create();
    $cancelled = Order::factory()->cancelled()->create();

    Livewire::test(ListOrders::class)
        ->filterTable('status', OrderStatus::Cancelled->value)
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$placed]);
});

it('filters by payment combination', function () {
    $wallet = Order::factory()->create();
    $stripe = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'wallet_amount' => 0]);
    $paypal = Order::factory()->create(['payment_method' => PaymentMethod::PayPal, 'wallet_amount' => 0]);
    $splitStripe = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'total' => 2000, 'subtotal' => 2000, 'shipping_fee' => 0, 'wallet_amount' => 500]);
    $splitPayPal = Order::factory()->create(['payment_method' => PaymentMethod::PayPal, 'total' => 2000, 'subtotal' => 2000, 'shipping_fee' => 0, 'wallet_amount' => 500]);

    Livewire::test(ListOrders::class)
        ->filterTable('payment', 'paypal')
        ->assertCanSeeTableRecords([$paypal])
        ->assertCanNotSeeTableRecords([$wallet, $stripe, $splitStripe, $splitPayPal])
        ->filterTable('payment', 'stripe')
        ->assertCanSeeTableRecords([$stripe])
        ->assertCanNotSeeTableRecords([$wallet, $paypal, $splitStripe, $splitPayPal])
        ->filterTable('payment', 'wallet_stripe')
        ->assertCanSeeTableRecords([$splitStripe])
        ->assertCanNotSeeTableRecords([$wallet, $stripe, $splitPayPal])
        ->filterTable('payment', 'wallet_paypal')
        ->assertCanSeeTableRecords([$splitPayPal])
        ->assertCanNotSeeTableRecords([$splitStripe])
        ->filterTable('payment', 'wallet')
        ->assertCanSeeTableRecords([$wallet])
        ->assertCanNotSeeTableRecords([$stripe, $splitStripe]);
});

it('filters by date range', function () {
    $old = Order::factory()->create(['created_at' => now()->subDays(10)]);
    $recent = Order::factory()->create(['created_at' => now()->subDays(2)]);
    $day = now()->subDays(5);
    $dayStart = Order::factory()->create(['created_at' => $day->copy()->startOfDay()]);
    $dayEnd = Order::factory()->create(['created_at' => $day->copy()->endOfDay()]);

    // Both ends are inclusive: orders at the very start and end of the picked day count.
    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => $day->toDateString(), 'until' => null])
        ->assertCanSeeTableRecords([$recent, $dayStart, $dayEnd])
        ->assertCanNotSeeTableRecords([$old])
        ->filterTable('created_at', ['from' => null, 'until' => $day->toDateString()])
        ->assertCanSeeTableRecords([$old, $dayStart, $dayEnd])
        ->assertCanNotSeeTableRecords([$recent]);
});

it('does not run a query per row', function () {
    Order::factory()->count(2)->create();
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListOrders::class);

        return count(DB::getQueryLog());
    };
    $few = $count();

    Order::factory()->count(8)->create();

    expect($count())->toBe($few);
});

it('shows the receipt, customer and payment on the order page', function () {
    $shopper = User::factory()->create(['name' => 'Sam Shopper']);
    $order = Order::factory()->for($shopper)->create([
        'payment_method' => PaymentMethod::Stripe, 'subtotal' => 1234, 'shipping_fee' => 678, 'total' => 1912, 'wallet_amount' => 412,
    ]);
    OrderItem::factory()->for($order)->create(['product_name' => 'Saved Widget', 'unit_price' => 617, 'quantity' => 2, 'line_total' => 1234]);
    Payment::factory()->forOrder($order)->stripe()->succeeded()->create([
        'card_brand' => 'visa', 'card_last4' => '4242', 'provider_payment_id' => 'pi_abc123',
    ]);

    Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful()
        ->assertSee('Saved Widget')
        ->assertSee('$6.17')
        ->assertSee('$12.34')
        ->assertSee('$6.78')
        ->assertSee('$19.12')
        ->assertSee('$4.12')
        ->assertSee('$15.00')
        ->assertSee('Sam Shopper')
        ->assertSee(CustomerResource::getUrl('view', ['record' => $shopper]))
        ->assertSee('Wallet + Stripe')
        ->assertSee('Visa ending in 4242')
        ->assertSee('Stripe payment pi_abc123')
        ->assertSee('https://dashboard.stripe.com/test/payments/pi_abc123');
});

it('shows a wallet order without a provider link', function () {
    $order = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful()
        ->assertSee('Cartopia wallet')
        ->assertDontSee('Paid with wallet')
        ->assertDontSee('dashboard.stripe.com');
});

it('has no create, edit or delete pages or actions', function () {
    $order = Order::factory()->create();

    expect(array_keys(OrderResource::getPages()))->toBe(['index', 'view'])
        ->and(OrderResource::canCreate())->toBeFalse()
        ->and(OrderResource::canEdit($order))->toBeFalse()
        ->and(OrderResource::canDelete($order))->toBeFalse();
});

it('hides the orders screens from shoppers', function () {
    $order = Order::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(OrderResource::getUrl('index'))->assertNotFound();
    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertNotFound();
});

it('sends guests to the login page', function () {
    $order = Order::factory()->create();
    auth()->logout();

    $this->get(OrderResource::getUrl('index'))->assertRedirect();
    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertRedirect();
});

it('shows 0 items for an order with no items', function () {
    $order = Order::factory()->create();

    Livewire::test(ListOrders::class)
        ->assertTableColumnStateSet('items_sum_quantity', 0, $order);
});
