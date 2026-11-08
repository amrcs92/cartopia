<?php

namespace App\Listeners;

use App\Enums\WalletTransactionType;
use App\Models\StoreSettings;
use App\Models\User;
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

        // Admins set the amount in the panel. Zero means no bonus and no ledger line.
        $credit = StoreSettings::current()->signup_credit;

        if ($credit->cents() === 0) {
            return;
        }

        $this->wallet->credit(
            $event->user,
            $credit,
            WalletTransactionType::SignupBonus,
            'Welcome to Cartopia',
        );
    }
}
