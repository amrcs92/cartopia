<?php

use App\Actions\Admin\AdjustWallet;
use App\Enums\WalletTransactionType;
use App\Exceptions\AdminActionRefused;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;

it('credits a customer and records the admin and reason', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->withBalance(Money::fromDollars('12.00'))->create();

    $transaction = app(AdjustWallet::class)->handle($customer, $admin, Money::fromDollars('5.00'), true, 'Goodwill credit');

    expect($transaction->type)->toBe(WalletTransactionType::AdminAdjustment)
        ->and($transaction->description)->toBe('Goodwill credit')
        ->and($transaction->admin_id)->toBe($admin->id)
        ->and($transaction->admin->is($admin))->toBeTrue()
        ->and($transaction->amount->cents())->toBe(500)
        ->and($customer->fresh()->balance->cents())->toBe(1700);
});

it('debits a customer', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->withBalance(Money::fromDollars('12.00'))->create();

    $transaction = app(AdjustWallet::class)->handle($customer, $admin, Money::fromDollars('5.00'), false, 'Duplicate bonus');

    expect($transaction->amount->cents())->toBe(-500)
        ->and($transaction->admin_id)->toBe($admin->id)
        ->and($customer->fresh()->balance->cents())->toBe(700);
});

it('refuses a debit larger than the balance and writes nothing', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->withBalance(Money::fromDollars('12.00'))->create(['name' => 'Ali']);
    $before = WalletTransaction::count();

    expect(fn () => app(AdjustWallet::class)->handle($customer, $admin, Money::fromCents(1201), false, 'Too much'))
        ->toThrow(AdminActionRefused::class, "Ali's balance is $12.00, so you can debit at most $12.00.");

    expect(WalletTransaction::count())->toBe($before)
        ->and($customer->fresh()->balance->cents())->toBe(1200);
});

it('uses the current balance in the refusal, not a stale model', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->withBalance(Money::fromDollars('12.00'))->create(['name' => 'Ali']);
    $stale = User::find($customer->id);

    app(AdjustWallet::class)->handle($customer, $admin, Money::fromDollars('2.00'), false, 'Spent elsewhere');

    expect(fn () => app(AdjustWallet::class)->handle($stale, $admin, Money::fromDollars('11.00'), false, 'Too much'))
        ->toThrow(AdminActionRefused::class, "Ali's balance is $10.00, so you can debit at most $10.00.");
});

it('rejects amounts outside the allowed range', function (int $cents) {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->withBalance(Money::fromDollars('900.00'))->create();

    expect(fn () => app(AdjustWallet::class)->handle($customer, $admin, Money::fromCents($cents), true, 'Reason'))
        ->toThrow(InvalidArgumentException::class);
})->with([0, 50_001]);

it('accepts the amount limits', function (int $cents) {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    app(AdjustWallet::class)->handle($customer, $admin, Money::fromCents($cents), true, 'Reason');

    expect($customer->fresh()->balance->cents())->toBe($cents);
})->with([1, 50_000]);

it('rejects an empty or overlong reason', function (string $reason) {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    expect(fn () => app(AdjustWallet::class)->handle($customer, $admin, Money::fromDollars('1.00'), true, $reason))
        ->toThrow(InvalidArgumentException::class);
})->with(['', '   ', fn () => str_repeat('a', 121)]);

it('accepts a reason of exactly 120 characters', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    $transaction = app(AdjustWallet::class)->handle($customer, $admin, Money::fromDollars('1.00'), true, str_repeat('a', 120));

    expect($transaction->description)->toHaveLength(120);
});
