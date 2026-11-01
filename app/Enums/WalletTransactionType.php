<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case SignupBonus = 'signup_bonus';
    case TopUp = 'top_up';
    case Purchase = 'purchase';
    case AdminAdjustment = 'admin_adjustment';
    case Release = 'release';

    public function label(): string
    {
        return match ($this) {
            self::SignupBonus => 'Signup bonus',
            self::TopUp => 'Top-up',
            self::Purchase => 'Purchase',
            self::AdminAdjustment => 'Adjustment',
            self::Release => 'Returned',
        };
    }
}
