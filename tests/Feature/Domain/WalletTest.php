<?php

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Wallet;

function wallet(): Wallet
{
    return app(Wallet::class);
}

it('credits and records balance_after', function () {
    $user = User::factory()->create();

    wallet()->credit($user, Money::fromCents(2550), WalletTransactionType::TopUp);

    expect($user->balance->cents())->toBe(2550)
        ->and($user->fresh()->balance->cents())->toBe(2550);
    $row = $user->walletTransactions()->sole();
    expect($row->amount->cents())->toBe(2550)
        ->and($row->balance_after->cents())->toBe(2550);
});

it('debits and links the order', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $order = Order::factory()->for($user)->create();

    $tx = wallet()->debit($user, Money::fromCents(1274), WalletTransactionType::Purchase, null, $order);

    expect($user->balance->cents())->toBe(8726)
        ->and($tx->amount->cents())->toBe(-1274)
        ->and($tx->order_id)->toBe($order->id)
        ->and($tx->balance_after->cents())->toBe(8726);
});

it('refuses to overdraw and changes nothing', function () {
    $user = User::factory()->withBalance(Money::fromCents(100))->create();
    $count = $user->walletTransactions()->count();

    expect(fn () => wallet()->debit($user, Money::fromCents(101), WalletTransactionType::Purchase))
        ->toThrow(InsufficientBalance::class);

    expect($user->fresh()->balance->cents())->toBe(100)
        ->and($user->balance->cents())->toBe(100)
        ->and($user->walletTransactions()->count())->toBe($count);
});

it('rejects non-positive amounts', function () {
    $user = User::factory()->create();

    expect(fn () => wallet()->credit($user, Money::zero(), WalletTransactionType::TopUp))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => wallet()->debit($user, Money::fromCents(-5), WalletTransactionType::Purchase))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps balance equal to the ledger sum', function () {
    mt_srand(42);
    $user = User::factory()->create();

    for ($i = 0; $i < 50; $i++) {
        $amount = Money::fromCents(mt_rand(1, 5000));
        try {
            if (mt_rand(0, 1) === 0) {
                wallet()->credit($user, $amount, WalletTransactionType::TopUp);
            } else {
                wallet()->debit($user, $amount, WalletTransactionType::Purchase);
            }
        } catch (InsufficientBalance) {
            // expected for some draws
        }

        expect($user->fresh()->balance->cents())->toBeGreaterThanOrEqual(0);
    }

    expect($user->fresh()->balance->cents())
        ->toBe((int) $user->walletTransactions()->sum('amount'));
});
