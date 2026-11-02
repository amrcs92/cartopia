<?php

use App\Actions\Admin\SetAdminAccess;
use App\Exceptions\AdminActionRefused;
use App\Models\User;

it('grants admin access', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $result = app(SetAdminAccess::class)->handle($target, $admin, true);

    expect($result->is_admin)->toBeTrue()
        ->and($target->fresh()->is_admin)->toBeTrue();
});

it('removes another admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    $result = app(SetAdminAccess::class)->handle($target, $admin, false);

    expect($result->is_admin)->toBeFalse()
        ->and($target->fresh()->is_admin)->toBeFalse();
});

it('does nothing when the access is already as requested', function () {
    $admin = User::factory()->admin()->create();
    $shopper = User::factory()->create();
    $other = User::factory()->admin()->create();

    expect(app(SetAdminAccess::class)->handle($other, $admin, true)->is_admin)->toBeTrue()
        ->and(app(SetAdminAccess::class)->handle($shopper, $admin, false)->is_admin)->toBeFalse();
});

it('refuses to remove your own access', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect(fn () => app(SetAdminAccess::class)->handle($admin, $admin, false))
        ->toThrow(AdminActionRefused::class, "You can't remove your own admin access.");

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('keeps the last admin when a stale tab removes them', function () {
    $a = User::factory()->admin()->create();
    $b = User::factory()->admin()->create();

    app(SetAdminAccess::class)->handle($a, $b, false);

    expect(fn () => app(SetAdminAccess::class)->handle($b, $a, false))
        ->toThrow(AdminActionRefused::class, 'Cartopia needs at least one admin.');

    expect($b->fresh()->is_admin)->toBeTrue();
});

it('only lets an admin change admin access', function () {
    $admin = User::factory()->admin()->create();
    $shopper = User::factory()->create();
    $target = User::factory()->create();

    expect(fn () => app(SetAdminAccess::class)->handle($target, $shopper, true))
        ->toThrow(AdminActionRefused::class, 'Only admins can change admin access.');

    expect($target->fresh()->is_admin)->toBeFalse();
});
