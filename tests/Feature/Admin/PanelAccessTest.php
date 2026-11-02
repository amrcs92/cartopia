<?php

use App\Models\User;

it('lets an admin into the panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('refuses a logged-in shopper', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('sends a guest to log in', function () {
    $this->get('/admin')->assertRedirect();
});
