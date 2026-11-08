<?php

use App\Enums\WalletTransactionType;
use App\Models\StoreSettings;
use App\Models\User;
use App\Support\Money;

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

    expect($user->balance->format())->toBe('$20.00')
        ->and($user->walletTransactions()->where('type', WalletTransactionType::SignupBonus)->count())->toBe(1)
        ->and($user->walletTransactions()->count())->toBe(1)
        ->and($user->phone)->toBe('01000000000')
        ->and($user->address)->toBe('Alexandria')
        ->and($user->is_admin)->toBeFalse();
});

it('grants the sign-up credit set in the panel', function () {
    StoreSettings::current()->update(['signup_credit' => Money::fromCents(2550)]);

    $this->post(route('register.store'), registrationPayload())->assertSessionHasNoErrors();

    $user = User::where('email', 'amr@example.com')->firstOrFail();

    expect($user->balance->format())->toBe('$25.50')
        ->and($user->walletTransactions()->sole()->amount->cents())->toBe(2550);
});

it('grants nothing and writes no ledger line when the sign-up credit is zero', function () {
    StoreSettings::current()->update(['signup_credit' => Money::zero()]);

    $this->post(route('register.store'), registrationPayload())->assertSessionHasNoErrors();

    $user = User::where('email', 'amr@example.com')->firstOrFail();

    expect($user->balance->cents())->toBe(0)
        ->and($user->walletTransactions()->count())->toBe(0);
});

it('starts at $20 and recreates the row if it goes missing', function () {
    expect(StoreSettings::current()->signup_credit->cents())->toBe(2000);

    StoreSettings::query()->delete();

    expect(StoreSettings::current()->signup_credit->cents())->toBe(StoreSettings::DEFAULT_SIGNUP_CREDIT_CENTS)
        ->and(StoreSettings::query()->count())->toBe(1);
});

it('registers without phone and address', function () {
    $this->post(route('register.store'), registrationPayload())->assertSessionHasNoErrors();

    $user = User::where('email', 'amr@example.com')->firstOrFail();

    expect($user->phone)->toBeNull()
        ->and($user->address)->toBeNull()
        ->and($user->balance->cents())->toBe(2000);
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
