<?php

namespace App\Actions;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Wallet\Wallet;
use InvalidArgumentException;

class TopUpWallet
{
    public const int MIN_CENTS = 500;

    public const int MAX_CENTS = 50000;

    public function __construct(private readonly Wallet $wallet) {}

    public function handle(User $user, Money $amount): WalletTransaction
    {
        if ($amount->cents() < self::MIN_CENTS || $amount->cents() > self::MAX_CENTS) {
            throw new InvalidArgumentException('Top-up must be between $5.00 and $500.00.');
        }

        return $this->wallet->credit($user, $amount, WalletTransactionType::TopUp, 'Wallet top-up');
    }
}
