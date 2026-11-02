<?php

use App\Actions\Admin\CheckPaymentWithProvider;
use App\Actions\Admin\ProviderCheck;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProviderCheckOutcome;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\Cancelled;
use App\Payments\Outcomes\StillPending;
use App\Payments\Outcomes\Succeeded;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists payments with the customer, purpose, method, amount, status and reference', function () {
    $shopper = User::factory()->create(['name' => 'Sam Shopper']);
    $order = Order::factory()->for($shopper)->create(['total' => 2000, 'subtotal' => 2000, 'shipping_fee' => 0, 'wallet_amount' => 0]);
    $orderPayment = Payment::factory()->forOrder($order)->stripe()->create(['provider_payment_id' => 'pi_abc123']);
    $topUp = Payment::factory()->topUp(2500)->paypal()->succeeded()->create(['user_id' => $shopper->id]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$orderPayment, $topUp])
        ->assertTableColumnStateSet('id', $orderPayment->id, $orderPayment)
        ->assertTableColumnStateSet('user.name', 'Sam Shopper', $orderPayment)
        ->assertTableColumnStateSet('purpose', "Order #{$order->id}", $orderPayment)
        ->assertTableColumnStateSet('purpose', 'Top-up', $topUp)
        ->assertTableColumnStateSet('method', 'Stripe', $orderPayment)
        ->assertTableColumnStateSet('method', 'PayPal', $topUp)
        ->assertTableColumnStateSet('amount', '$20.00', $orderPayment)
        ->assertTableColumnStateSet('status', 'Pending', $orderPayment)
        ->assertTableColumnStateSet('status', 'Succeeded', $topUp)
        ->assertTableColumnStateSet('reference', 'Stripe payment pi_abc123', $orderPayment)
        ->assertTableColumnExists('created_at');
});

it('lists newest first and leaves out wallet rows', function () {
    $older = Payment::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Payment::factory()->paypal()->create(['created_at' => now()]);
    $wallet = Payment::factory()->create(['method' => PaymentMethod::Wallet]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertCanNotSeeTableRecords([$wallet]);
});

it('filters by status, method and purpose', function () {
    $pending = Payment::factory()->stripe()->create();
    $succeeded = Payment::factory()->paypal()->succeeded()->create();
    $orderPayment = Payment::factory()->forOrder(Order::factory()->create())->stripe()->create();

    Livewire::test(ListPayments::class)
        ->filterTable('status', PaymentStatus::Succeeded->value)
        ->assertCanSeeTableRecords([$succeeded])
        ->assertCanNotSeeTableRecords([$pending, $orderPayment])
        ->removeTableFilters()
        ->filterTable('method', PaymentMethod::PayPal->value)
        ->assertCanSeeTableRecords([$succeeded])
        ->assertCanNotSeeTableRecords([$pending, $orderPayment])
        ->removeTableFilters()
        ->filterTable('purpose', PaymentPurpose::Order->value)
        ->assertCanSeeTableRecords([$orderPayment])
        ->assertCanNotSeeTableRecords([$pending, $succeeded]);
});

it('shows only pending payments older than 30 minutes under Needs attention', function () {
    $this->travelTo(now());
    $stale = Payment::factory()->create(['created_at' => now()->subMinutes(31)]);
    $recent = Payment::factory()->create(['created_at' => now()->subMinutes(29)]);
    $settled = Payment::factory()->succeeded()->create(['created_at' => now()->subHours(2)]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$stale, $recent, $settled])
        ->set('activeTab', 'needs-attention')
        ->assertCanSeeTableRecords([$stale])
        ->assertCanNotSeeTableRecords([$recent, $settled]);
});

it('does not run a query per row', function () {
    Payment::factory()->count(2)->forOrder(Order::factory()->create())->create();
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListPayments::class);

        return count(DB::getQueryLog());
    };
    $few = $count();

    foreach (range(1, 6) as $_) {
        Payment::factory()->forOrder(Order::factory()->create())->create();
    }

    expect($count())->toBe($few);
});

it('shows the payment details, customer, order and provider link', function () {
    $shopper = User::factory()->create(['name' => 'Sam Shopper']);
    $order = Order::factory()->for($shopper)->create(['total' => 2000, 'subtotal' => 2000, 'shipping_fee' => 0, 'wallet_amount' => 0]);
    $payment = Payment::factory()->forOrder($order)->stripe()->succeeded()->create([
        'card_brand' => 'visa', 'card_last4' => '4242', 'provider_payment_id' => 'pi_abc123',
    ]);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSuccessful()
        ->assertSee('Stripe')
        ->assertSee('Succeeded')
        ->assertSee('$20.00')
        ->assertSee("Order #{$order->id}")
        ->assertSee('Visa ending in 4242')
        ->assertSee('Stripe payment pi_abc123')
        ->assertSee('Sam Shopper')
        ->assertSee(CustomerResource::getUrl('view', ['record' => $shopper]))
        ->assertSee(OrderResource::getUrl('view', ['record' => $order]))
        ->assertSee('https://dashboard.stripe.com/test/payments/pi_abc123');
});

it('names a pending payment by its checkout session or PayPal order, on the list and its page', function (PaymentMethod $method, string $reference, string $shown) {
    $payment = Payment::factory()->state(['method' => $method])->withReference($reference)->create();

    Livewire::test(ListPayments::class)
        ->assertTableColumnStateSet('reference', $shown, $payment);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSee('Provider reference')
        ->assertSee($shown);
})->with([
    'Stripe' => [PaymentMethod::Stripe, 'cs_test_a1b2c3', 'Stripe checkout session cs_test_a1b2c3'],
    'PayPal' => [PaymentMethod::PayPal, '5O190127TN364715T', 'PayPal order 5O190127TN364715T'],
]);

it('shows a top-up without an order or provider link', function () {
    $payment = Payment::factory()->topUp(1500)->paypal()->create();

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSuccessful()
        ->assertSee('Top-up')
        ->assertDontSee('sandbox.paypal.com');
});

it('offers Check with provider only while the payment is pending', function () {
    $pending = Payment::factory()->create();
    $settled = Payment::factory()->succeeded()->create();

    Livewire::test(ViewPayment::class, ['record' => $pending->getKey()])
        ->assertActionVisible('checkWithProvider');
    Livewire::test(ViewPayment::class, ['record' => $settled->getKey()])
        ->assertActionHidden('checkWithProvider');
});

it('shows the provider answer as a notification and refreshes the page', function () {
    Mail::fake();
    $fake = PaymentGateways::fake();
    $payment = Payment::factory()->topUp(2500)->paypal()->withReference('ref_1')->create();
    $fake->willLookUp($fake->paid($payment));

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified(Notification::make()->success()->title('Paid at PayPal. The top-up is credited.'))
        ->assertSee('Succeeded');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('shows each provider answer in its tone', function (Closure $answer, string $status, string $message) {
    Mail::fake();
    $fake = PaymentGateways::fake();
    $payment = Payment::factory()->topUp(2500)->stripe()->withReference('ref_1')->create();
    $fake->willLookUp($answer($payment));

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified(Notification::make()->status($status)->title($message));
})->with([
    'paid' => [fn (Payment $payment) => new Succeeded('pi_1', $payment->amount, 'usd'), 'success', 'Paid at Stripe. The top-up is credited.'],
    'still pending' => [fn () => new StillPending, 'warning', 'Still pending at Stripe.'],
    'a different amount' => [fn () => new Succeeded('pi_1', Money::fromCents(1), 'usd'), 'danger', 'Stripe reported a different amount. The payment is marked failed.'],
    'gone' => [fn () => new Cancelled, 'danger', "Stripe no longer has this payment. It's closed."],
]);

it('shows a payment that settled meanwhile as a success', function () {
    $payment = Payment::factory()->stripe()->withReference('ref_1')->create();
    // Settling between the page's load and the check's lock is a race, so the answer is stood in.
    $this->mock(CheckPaymentWithProvider::class)->shouldReceive('handle')
        ->andReturn(new ProviderCheck(ProviderCheckOutcome::AlreadySettled, 'This payment is already succeeded.'));

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified(Notification::make()->success()->title('This payment is already succeeded.'));
});

it('says when the provider still has the payment pending', function () {
    $fake = PaymentGateways::fake();
    $payment = Payment::factory()->stripe()->withReference('ref_1')->create();
    $fake->willLookUp(new StillPending);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified(Notification::make()->warning()->title('Still pending at Stripe.'));
});

it('warns when the provider cannot be reached', function (PaymentMethod $method, string $name) {
    $fake = PaymentGateways::fake();
    $payment = Payment::factory()->state(['method' => $method])->withReference('ref_1')->create();
    $fake->willLookUp(new PaymentProviderUnavailable('down'));

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified("{$name} couldn't be reached. Try again in a moment.");
})->with([
    'Stripe' => [PaymentMethod::Stripe, 'Stripe'],
    'PayPal' => [PaymentMethod::PayPal, 'PayPal'],
]);

it('warns when the provider rejects the check', function () {
    $fake = PaymentGateways::fake();
    $payment = Payment::factory()->paypal()->withReference('ref_1')->create();
    $fake->willLookUp(new PaymentProviderRejected('no'));

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('checkWithProvider')
        ->assertNotified("PayPal couldn't check this payment. Try again in a moment.");
});

it('has no create, edit or delete pages or actions', function () {
    $payment = Payment::factory()->create();

    expect(array_keys(PaymentResource::getPages()))->toBe(['index', 'view'])
        ->and(PaymentResource::canCreate())->toBeFalse()
        ->and(PaymentResource::canEdit($payment))->toBeFalse()
        ->and(PaymentResource::canDelete($payment))->toBeFalse();
});

it('hides the payments screens from shoppers', function () {
    $payment = Payment::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(PaymentResource::getUrl('index'))->assertNotFound();
    $this->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertNotFound();
});

it('sends guests to the login page', function () {
    $payment = Payment::factory()->create();
    auth()->logout();

    $this->get(PaymentResource::getUrl('index'))->assertRedirect();
    $this->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertRedirect();
});
