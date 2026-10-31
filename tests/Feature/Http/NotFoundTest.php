<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the not-found page for unknown pages and missing products', function (string $url) {
    $this->get($url)->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('errors/NotFoundPage'));
})->with(['/no-such-page', '/products/no-such-product']);

it('shares the signed-in user and the basket on an unknown URL', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/NotFoundPage')
            ->where('auth.user.id', $user->id)
            ->where('cart.count', 0));
});

it('shares a null user to guests on an unknown URL', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/NotFoundPage')
            ->where('auth.user', null)
            ->where('cart.count', 0));
});

it('keeps JSON 404s as JSON', function () {
    $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
});

it('shares the signed-in user on a missing model binding 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/products/no-such-product')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/NotFoundPage')
            ->where('auth.user.id', $user->id));
});

it('applies the dark theme on a missing model binding 404', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get('/products/no-such-product')
        ->assertNotFound()
        ->assertSee('class="dark"', false);
});
