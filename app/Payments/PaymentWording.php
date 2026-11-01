<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;

/**
 * The customer-facing sentences about how an order was paid.
 */
final class PaymentWording
{
    public static function placedMessage(Order $order): string
    {
        // The hosted share; for a wallet-only order it is the whole total and unused.
        $hosted = $order->total->subtract($order->wallet_amount)->format();

        return match ($order->payment_method) {
            PaymentMethod::Wallet => "Order placed. {$order->total->format()} was paid from your wallet.",
            PaymentMethod::Stripe => "Order placed. Your card was charged {$hosted}.",
            PaymentMethod::PayPal => "Order placed. Your PayPal payment of {$hosted} was approved.",
        };
    }
}
