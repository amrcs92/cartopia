<?php

use App\Enums\WalletTransactionType;
use App\Models\User;

function registrationPayload(array $extra = []): array
{
    return [
        'name' => 'Amr Tester',
        'email' => 'amr@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$extra,
    ];
}

it('grants exactly one signup bonus', function () {
    $this->post(route('register.store'), registrationPayload([
        'phone' => '01000000000',
        'address' => 'Alexandria',
    ]))->assertSessionHasNoErrors();

    $user = User::where('email', 'amr@example.com')->firstOrFail();

    expect($user->balance->format())->toBe('$100.00')
        ->and($user->walletTransactions()->where('type', WalletTransactionType::SignupBonus)->count())->toBe(1)
        ->and($user->walletTransactions()->count())->toBe(1)
        ->and($user->phone)->toBe('01000000000')
        ->and($user->address)->toBe('Alexandria')
        ->and($user->is_admin)->toBeFalse();
});

it('registers without phone and address', function () {
    $this->post(route('register.store'), registrationPayload())->assertSessionHasNoErrors();

    $user = User::where('email', 'amr@example.com')->firstOrFail();

    expect($user->phone)->toBeNull()
        ->and($user->address)->toBeNull()
        ->and($user->balance->cents())->toBe(10000);
});

it('updates phone and address from profile settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '0123',
        'address' => '1 Corniche Rd',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->phone)->toBe('0123')
        ->and($user->fresh()->address)->toBe('1 Corniche Rd');
});
