<?php

use App\Actions\PlaceOrder;
use App\Actions\TopUpWallet;
use App\Cart\Cart;
use App\Enums\WalletTransactionType;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

it('tops up and records the ledger', function () {
    $user = User::factory()->withBalance(Money::fromCents(1000))->create();

    $this->actingAs($user)
        ->post(route('wallet.top-ups.store'), ['amount' => '25.50'])
        ->assertRedirect(route('wallet.show'));

    $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast', ['type' => 'success', 'message' => 'Added $25.50 to your balance.']));

    $row = WalletTransaction::query()->latest('id')->first();

    expect($user->fresh()->balance->cents())->toBe(3550)
        ->and($row->type)->toBe(WalletTransactionType::TopUp)
        ->and($row->amount->cents())->toBe(2550)
        ->and($row->balance_after->cents())->toBe(3550);
});

it('rejects invalid top-up amounts', function (string $amount) {
    $user = User::factory()->create();
    $before = WalletTransaction::count();

    $this->actingAs($user)
        ->post(route('wallet.top-ups.store'), ['amount' => $amount])
        ->assertSessionHasErrors('amount');

    expect(WalletTransaction::count())->toBe($before);
})->with(['4.99', '500.01', '5.001', 'abc', '', '.5', '+5', '100000000000000000']);

it('accepts boundary top-up amounts', function (string $amount) {
    $this->actingAs(User::factory()->create())
        ->post(route('wallet.top-ups.store'), ['amount' => $amount])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('wallet.show'));
})->with(['5', '500']);

it('guards its own limits in the action', function (int $cents) {
    app(TopUpWallet::class)->handle(User::factory()->create(), Money::fromCents($cents));
})->with([499, 50001, 0, -500])->throws(InvalidArgumentException::class);

it('throttles top-ups', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '5'])->assertRedirect();
    }

    $this->actingAs($user)->post(route('wallet.top-ups.store'), ['amount' => '5'])->assertStatus(429);
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
    $this->post(route('wallet.top-ups.store'), ['amount' => '5'])->assertRedirect(route('login'));
});
