<?php

use App\Models\User;

it('lets an admin into the panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('shows a shopper the not-found page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertNotFound();
});

it('sends a guest to the store login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('has no Filament login page', function () {
    $this->get('/admin/login')->assertNotFound();
});

it('sends an admin back to the panel after the store login', function () {
    $admin = User::factory()->admin()->create();

    $this->get('/admin');

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin');
});

it('shows the logo and the Cartopia name in the panel brand', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSee(asset('favicon.svg'), false)
        ->assertSee('>Cartopia</span>', false);
});
