<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Mail\PaymentProblemMail;
use App\Models\Order;
use App\Models\Payment;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * The one place a payment leaves pending without succeeding (spec 4.3).
 * A payment that is no longer pending is returned untouched.
 */
class ClosePayment
{
    public function __construct(private readonly Wallet $wallet) {}

    public function handle(Payment $payment, PaymentStatus $status, bool $notify): Payment
    {
        if (! in_array($status, [PaymentStatus::Cancelled, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            throw new InvalidArgumentException("A payment cannot be closed as [{$status->value}].");
        }

        return DB::transaction(function () use ($payment, $status, $notify): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return $locked;
            }

            $locked->update(['status' => $status, 'completed_at' => now()]);

            $order = $locked->order;

            if ($locked->purpose === PaymentPurpose::Order && $order instanceof Order) {
                $order->update(['status' => OrderStatus::Cancelled]);

                // The held wallet share goes back exactly once: pending only ends once, under the payment lock.
                if ($order->wallet_amount->cents() > 0) {
                    $this->wallet->credit($order->user, $order->wallet_amount, WalletTransactionType::Release, "Order #{$order->id} not paid, balance returned", $order);
                }
            }

            // Only the call that closes the payment gets here, so a repeat queues nothing.
            if ($notify) {
                Mail::to($locked->user)->queue(new PaymentProblemMail($locked));
            }

            return $locked;
        });
    }
}
