import { formatCents } from '@/modules/cart/money';

export type HostedMethod = 'stripe' | 'paypal';

// Mirrors PaymentGateways::MIN_CENTS in PHP: the smallest amount the card and
// PayPal providers accept.
export const MIN_HOSTED_CENTS = 50;

// The text on the main checkout button, for the chosen method.
export function buttonLabel(
    method: 'wallet' | HostedMethod,
    amountCents: number,
): string {
    const amount = formatCents(amountCents);

    if (method === 'stripe') {
        return `Pay ${amount} with Stripe`;
    }

    if (method === 'paypal') {
        return `Pay ${amount} with PayPal`;
    }

    return `Place order and pay ${amount}`;
}

export function noteFor(method: HostedMethod): string {
    return method === 'stripe'
        ? 'Credit or debit card'
        : 'Pay with your PayPal account';
}
