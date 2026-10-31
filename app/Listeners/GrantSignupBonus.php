<?php

namespace App\Listeners;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Auth\Events\Registered;

class GrantSignupBonus
{
    public function __construct(private readonly Wallet $wallet) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->wallet->credit(
            $event->user,
            Money::fromCents(10000),
            WalletTransactionType::SignupBonus,
            'Welcome to Cartopia',
        );
    }
}
