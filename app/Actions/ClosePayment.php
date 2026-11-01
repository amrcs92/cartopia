<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place a payment leaves pending without succeeding (spec 4.3).
 * A payment that is no longer pending is returned untouched.
 */
class ClosePayment
{
    public function handle(Payment $payment, PaymentStatus $status, bool $notify): Payment
    {
        if (! in_array($status, [PaymentStatus::Cancelled, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            throw new InvalidArgumentException("A payment cannot be closed as [{$status->value}].");
        }

        return DB::transaction(function () use ($payment, $status): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return $locked;
            }

            $locked->update(['status' => $status, 'completed_at' => now()]);

            if ($locked->purpose === PaymentPurpose::Order && $locked->order instanceof Order) {
                $locked->order->update(['status' => OrderStatus::Cancelled]);
                // Task 20 returns the order's wallet share here (a Release row).
            }

            // sends PaymentProblemMail from phase 5 (when $notify is true)

            return $locked;
        });
    }
}
