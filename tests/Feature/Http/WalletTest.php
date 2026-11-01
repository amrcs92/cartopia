<?php

use App\Actions\PlaceOrder;
use App\Actions\StartTopUpPayment;
use App\Cart\Cart;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

it('sends a top-up to the provider', function () {
    $user = User::factory()->withBalance(Money::fromCents(1000))->create();

    $this->actingAs($user)
        ->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'paypal'])
        ->assertRedirect(route('payments.return', Payment::query()->firstOrFail()));

    $payment = Payment::query()->firstOrFail();

    expect($payment->purpose)->toBe(PaymentPurpose::TopUp)
        ->and($payment->method)->toBe(PaymentMethod::PayPal)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount->cents())->toBe(2500)
        ->and($payment->currency)->toBe('usd')
        ->and($payment->order_id)->toBeNull()
        ->and($payment->provider_reference)->toBe("fake_{$payment->id}")
        ->and($user->fresh()->balance->cents())->toBe(1000)
        ->and($this->fake->started[0][1]->description)->toBe('Cartopia wallet top-up');
});

it('answers an Inertia top-up with the provider location', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'stripe'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('payments.return', Payment::query()->firstOrFail()));
});

it('credits a top-up once on return', function () {
    $user = User::factory()->withBalance(Money::fromCents(1000))->create();
    $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'paypal']);
    $payment = Payment::query()->firstOrFail();

    $this->get(route('payments.return', $payment))->assertRedirect(route('wallet.show'));
    $this->get(route('payments.return', $payment))->assertRedirect(route('wallet.show'));

    $rows = WalletTransaction::query()->where('type', WalletTransactionType::TopUp)->get();

    expect($user->fresh()->balance->cents())->toBe(3500)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]->amount->cents())->toBe(2500)
        ->and($rows[0]->balance_after->cents())->toBe(3500)
        ->and($rows[0]->description)->toBe('Top-up with PayPal')
        ->and($rows[0]->payment_id)->toBe($payment->id)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('shows the success toast after a top-up returns', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->topUp(2500)->stripe()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('payments.return', $payment))->assertRedirect(route('wallet.show'));

    $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'success', 'message' => 'Added $25.00 to your wallet.']));
});

it('cancels a top-up back to the wallet page', function () {
    $user = User::factory()->withBalance(Money::fromCents(1000))->create();
    $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'stripe']);
    $payment = Payment::query()->firstOrFail();

    $this->get(route('payments.cancel', $payment))->assertRedirect(route('wallet.show'));

    $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'info', 'message' => 'Payment cancelled. Nothing was charged.'])
        ->missingFlash('checkoutError'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($user->fresh()->balance->cents())->toBe(1000);
});

it('leaves nothing behind when the provider is down', function () {
    $user = User::factory()->withBalance(Money::fromCents(1000))->create();
    $this->fake->failToStart();

    $this->actingAs($user)
        ->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'stripe'])
        ->assertRedirect(route('wallet.show'));

    $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'error', 'message' => "We couldn't reach Stripe. Try again in a moment."]));

    expect(Payment::count())->toBe(0)
        ->and($user->fresh()->balance->cents())->toBe(1000);
});

it('refuses a top-up without a method', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => '25'])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::count())->toBe(0);
});

it('refuses an unavailable method', function () {
    app()->forgetInstance(PaymentGateways::class);
    config(['services.paypal.client_id' => null, 'services.paypal.secret' => null, 'services.stripe.secret' => 'sk_test_x']);

    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'paypal'])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::count())->toBe(0);
});

it('refuses the wallet as a way to top up', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => '25', 'payment_method' => 'wallet'])
        ->assertSessionHasErrors('payment_method');
});

it('refuses a top-up outside $5 to $500', function (string $amount) {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => $amount, 'payment_method' => 'stripe'])
        ->assertSessionHasErrors('amount');

    expect(Payment::count())->toBe(0);
})->with(['4.99', '500.01', '5.001', 'abc', '', '.5', '+5', '100000000000000000']);

it('accepts boundary top-up amounts', function (string $amount) {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => $amount, 'payment_method' => 'stripe'])
        ->assertSessionHasNoErrors();

    expect(Payment::count())->toBe(1);
})->with(['5', '500']);

it('guards its own limits in the action', function (int $cents) {
    app(StartTopUpPayment::class)->handle(User::factory()->create(), Money::fromCents($cents), PaymentMethod::Stripe);
})->with([499, 50001, 0, -500])->throws(InvalidArgumentException::class);

it('throttles top-ups', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '5', 'payment_method' => 'stripe'])->assertRedirect();
    }

    $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '5', 'payment_method' => 'stripe'])->assertStatus(429);
});

it('shows the ledger newest first with signed amounts', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $cart = Cart::forUser($user);
    $cart->add(Product::factory()->create(['price' => 200]), 2);
    $cart->add(Product::factory()->create(['price' => 374]), 1);
    app(PlaceOrder::class)->handle($user, ShippingMethod::factory()->ups()->create());

    $this->actingAs($user)
        ->get(route('wallet.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/WalletPage')
            ->where('balance', '$87.26')
            ->has('transactions.data', 2)
            ->where('transactions.data.0.amount', '-$12.74')
            ->where('transactions.data.0.type', 'purchase')
            ->where('transactions.data.0.type_label', 'Purchase')
            ->where('transactions.data.0.balance_after', '$87.26')
            ->where('transactions.data.1.amount', '+$100.00')
            ->where('transactions.data.1.type', 'signup_bonus')
            ->has('transactions.data.0.created_at')
            ->where('transactions.meta.per_page', 15));
});

it('paginates the ledger at 15 per page and shows only own rows', function () {
    $user = User::factory()->create();
    foreach (range(1, 17) as $i) {
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => WalletTransactionType::TopUp,
            'amount' => 500, 'balance_after' => 500 * $i,
        ]);
    }
    WalletTransaction::create([
        'user_id' => User::factory()->create()->id, 'type' => WalletTransactionType::TopUp,
        'amount' => 500, 'balance_after' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('wallet.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 15)
            ->where('transactions.meta.total', 17));
});

it('lists a release row as a credit', function () {
    $user = User::factory()->create();
    WalletTransaction::create([
        'user_id' => $user->id, 'type' => WalletTransactionType::Release,
        'amount' => 210, 'balance_after' => 210,
        'description' => 'Order #5 not paid, balance returned',
    ]);

    $this->actingAs($user)
        ->get(route('wallet.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.data.0.type', 'release')
            ->where('transactions.data.0.type_label', 'Returned')
            ->where('transactions.data.0.amount', '+$2.10')
            ->where('transactions.data.0.description', 'Order #5 not paid, balance returned'));
});

it('redirects guests to login', function () {
    $this->get(route('wallet.show'))->assertRedirect(route('login'));
    $this->post(route('wallet.top-ups.store'), ['amount' => '5', 'payment_method' => 'stripe'])->assertRedirect(route('login'));
});
