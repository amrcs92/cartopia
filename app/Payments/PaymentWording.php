<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;

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
            PaymentMethod::Stripe => "Order placed. Your Stripe payment of {$hosted} was approved.",
            PaymentMethod::PayPal => "Order placed. Your PayPal payment of {$hosted} was approved.",
        };
    }

    public static function paidWith(Order $order): string
    {
        // Split orders (wallet plus a hosted method) are worded in a later task.
        return match ($order->payment_method) {
            PaymentMethod::Wallet => 'Cartopia wallet',
            PaymentMethod::Stripe => self::stripeCard($order->succeededPayment),
            PaymentMethod::PayPal => 'PayPal',
        };
    }

    /**
     * @return array{short: string, full: string}|null
     */
    public static function reference(Payment $payment): ?array
    {
        $id = $payment->provider_payment_id;
        if ($id === null || $id === '') {
            return null;
        }

        $label = match ($payment->method) {
            PaymentMethod::Stripe => 'Stripe payment',
            PaymentMethod::PayPal => 'PayPal capture',
            PaymentMethod::Wallet => null,
        };
        if ($label === null) {
            return null;
        }

        $short = mb_strlen($id) <= 12 ? $id : mb_substr($id, 0, 8).'…'.mb_substr($id, -4);

        return ['short' => "{$label} {$short}", 'full' => "{$label} {$id}"];
    }

    /**
     * The lines at the bottom of the slip, after the total.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function slipLines(Order $order, ?Money $balanceAfter): array
    {
        $hosted = $order->total->subtract($order->wallet_amount)->format();

        return match ($order->payment_method) {
            PaymentMethod::Wallet => $balanceAfter === null
                ? []
                : [['label' => 'Wallet balance after', 'value' => $balanceAfter->format()]],
            PaymentMethod::Stripe => [['label' => 'Paid through Stripe', 'value' => $hosted]],
            PaymentMethod::PayPal => [['label' => 'Paid through PayPal', 'value' => $hosted]],
        };
    }

    public static function tag(Order $order): string
    {
        return match ($order->payment_method) {
            PaymentMethod::Wallet => 'Paid with wallet',
            PaymentMethod::Stripe => 'Paid with Stripe',
            PaymentMethod::PayPal => 'Paid with PayPal',
        };
    }

    public static function cardBrand(?string $brand): string
    {
        return match (strtolower($brand ?? '')) {
            '' => 'Card',
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'discover' => 'Discover',
            default => ucfirst(strtolower($brand)),
        };
    }

    private static function stripeCard(?Payment $payment): string
    {
        if ($payment === null || $payment->card_last4 === null) {
            return 'Card, through Stripe';
        }

        return self::cardBrand($payment->card_brand)." ending in {$payment->card_last4}, through Stripe";
    }
}
