<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use LogicException;

/**
 * The customer-facing sentences about how an order was paid.
 */
final class PaymentWording
{
    public static function placedMessage(Order $order): string
    {
        return match ($order->payment_method) {
            PaymentMethod::Wallet => "Order placed. {$order->total->format()} was paid from your wallet.",
            default => throw new LogicException("No placed message for the {$order->payment_method->value} payment method yet."),
        };
    }
}
