<?php

namespace App\Actions\Admin;

use App\Enums\WalletTransactionType;
use App\Exceptions\AdminActionRefused;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An admin credits or debits a customer's wallet, always with a reason.
 */
class AdjustWallet
{
    public const MIN_CENTS = 1;

    public const MAX_CENTS = 50_000;

    public const MAX_REASON_LENGTH = 120;

    public function __construct(private readonly Wallet $wallet) {}

    /**
     * @throws AdminActionRefused
     */
    public function handle(User $customer, User $admin, Money $amount, bool $credit, string $reason): WalletTransaction
    {
        if ($amount->cents() < self::MIN_CENTS || $amount->cents() > self::MAX_CENTS) {
            throw new InvalidArgumentException('Amount must be between $0.01 and $500.00.');
        }

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new InvalidArgumentException('Reason must be 1 to '.self::MAX_REASON_LENGTH.' characters.');
        }

        if ($credit) {
            return $this->wallet->credit($customer, $amount, WalletTransactionType::AdminAdjustment, $reason, admin: $admin);
        }

        return DB::transaction(function () use ($customer, $admin, $amount, $reason): WalletTransaction {
            // Lock first so the balance in the refusal is the one the debit is checked against.
            $locked = User::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->balance->isLessThan($amount)) {
                throw new AdminActionRefused("{$locked->name}'s balance is {$locked->balance->format()}, so you can debit at most {$locked->balance->format()}.");
            }

            return $this->wallet->debit($customer, $amount, WalletTransactionType::AdminAdjustment, $reason, admin: $admin);
        });
    }
}
