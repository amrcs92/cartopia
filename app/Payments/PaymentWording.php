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
        $lead = $order->isSplit() ? "Order placed. {$order->wallet_amount->format()} came from your wallet and your" : 'Order placed. Your';

        return match ($order->payment_method) {
            PaymentMethod::Wallet => "Order placed. {$order->total->format()} was paid from your wallet.",
            PaymentMethod::Stripe => "{$lead} Stripe payment of {$hosted} was approved.",
            PaymentMethod::PayPal => "{$lead} PayPal payment of {$hosted} was approved.",
        };
    }

    public static function paidWith(Order $order): string
    {
        if ($order->isSplit()) {
            $hosted = $order->total->subtract($order->wallet_amount)->format();
            $part = match ($order->payment_method) {
                PaymentMethod::Stripe => self::stripeCardShort($order->succeededPayment),
                default => 'PayPal',
            };

            return "Cartopia wallet {$order->wallet_amount->format()} + {$part} {$hosted}";
        }

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
        $balance = $balanceAfter === null ? [] : [['label' => 'Wallet balance after', 'value' => $balanceAfter->format()]];

        if ($order->isSplit()) {
            $hostedLabel = $order->payment_method === PaymentMethod::Stripe ? 'Paid through Stripe' : 'Paid through PayPal';

            return [
                ['label' => 'Paid from wallet', 'value' => Money::zero()->subtract($order->wallet_amount)->format()],
                ['label' => $hostedLabel, 'value' => $hosted],
                ...$balance,
            ];
        }

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
        if ($order->isSplit()) {
            return $order->payment_method === PaymentMethod::Stripe ? 'Wallet + Stripe' : 'Wallet + PayPal';
        }

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

    private static function stripeCardShort(?Payment $payment): string
    {
        if ($payment === null || $payment->card_last4 === null) {
            return 'Stripe';
        }

        return self::cardBrand($payment->card_brand)." ending in {$payment->card_last4}";
    }

    private static function stripeCard(?Payment $payment): string
    {
        if ($payment === null || $payment->card_last4 === null) {
            return 'Card, through Stripe';
        }

        return self::cardBrand($payment->card_brand)." ending in {$payment->card_last4}, through Stripe";
    }
}
