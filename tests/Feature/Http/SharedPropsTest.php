<?php

use App\Cart\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

it('shares null user and zero cart for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('cart.count', 0)
        );
});

it('shares the formatted balance and balance in cents for users', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => '$100.00',
                'balance_cents' => 10000,
                'is_admin' => false,
            ])
        );
});

it('shares the cart item count', function () {
    $user = User::factory()->create();
    Cart::forUser($user)->add(Product::factory()->create(), 2);
    Cart::forUser($user)->add(Product::factory()->create(), 3);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 5));
});

it("counts a guest's session cart", function () {
    $product = Product::factory()->create();

    $this->withSession(['cart' => [$product->id => 4]])
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 4));
});

it('leaves the menu aisles out of a normal page load', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('menuAisles'));
});

it('sends the menu aisles when the menu asks for them', function () {
    $fruit = Category::factory()->create(['name' => 'Fruit', 'slug' => 'fruit']);
    Product::factory()->inCategory($fruit)->create();
    Category::factory()->create(['name' => 'Empty', 'slug' => 'empty']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('menuAisles', fn (Assert $reload) => $reload
                ->has('menuAisles', 1)
                ->where('menuAisles.0.slug', 'fruit')
                ->where('menuAisles.0.name', 'Fruit')
            )
        );
});

it('keeps bracketed query arrays as requested in the page url', function () {
    Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);

    $this->get('/products?aisle%5B%5D=drinks')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $url = $page->toArray()['url'];

            expect($url)->toContain('aisle%5B%5D=drinks')->not->toContain('aisle%5B0%5D');
        });
});

it('shares the available payment methods', function () {
    PaymentGateways::fake();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('paymentMethods', ['stripe', 'paypal']));
});

it('shares no payment methods without keys', function () {
    config(['services.stripe.secret' => null, 'services.paypal.client_id' => null, 'services.paypal.secret' => null]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('paymentMethods', []));
});
