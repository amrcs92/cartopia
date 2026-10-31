<?php

namespace App\Wallet;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code allowed to change a user's balance. Every change is
 * recorded as a ledger row so the balance always equals the ledger sum.
 */
class Wallet
{
    public function credit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null, ?Order $order = null, ?Payment $payment = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return $this->apply($user, $amount, $type, $description, $order, $payment);
    }

    /**
     * @throws InsufficientBalance
     */
    public function debit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null, ?Order $order = null): WalletTransaction
    {
        $this->assertPositive($amount);

        return $this->apply($user, Money::zero()->subtract($amount), $type, $description, $order, null);
    }

    /**
     * @param  Money  $signed  Positive to credit, negative to debit.
     */
    private function apply(User $user, Money $signed, WalletTransactionType $type, ?string $description, ?Order $order, ?Payment $payment): WalletTransaction
    {
        return DB::transaction(function () use ($user, $signed, $type, $description, $order, $payment): WalletTransaction {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $newBalance = $locked->balance->add($signed);

            if ($newBalance->isNegative()) {
                throw new InsufficientBalance('Insufficient balance.');
            }

            $transaction = WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $signed,
                'balance_after' => $newBalance,
                'order_id' => $order?->id,
                'payment_id' => $payment?->id,
                'description' => $description,
            ]);

            $locked->balance = $newBalance;
            $locked->save();

            $user->balance = $newBalance;
            $user->syncOriginalAttribute('balance');

            return $transaction;
        });
    }

    private function assertPositive(Money $amount): void
    {
        if ($amount->isNegative() || $amount->equals(Money::zero())) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
    }
}
