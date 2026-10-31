import type { ShippingMethod } from '@/types';

// Shipping methods have no type column, so a method is pickup when its name
// says so ("Pickup", "Pick-up", "Pick up"); every other method is delivery.
export function isPickup(method: ShippingMethod): boolean {
    return /pick\s*-?up/i.test(method.name);
}

export function feeLabel(method: ShippingMethod): string {
    return method.fee_cents === 0 ? 'Free' : method.fee;
}

/**
 * The "Choose how to get it" sentence: the mockup's wording when there is a
 * free pickup and at least one delivery, otherwise something neutral.
 */
export function deliverySentence(methods: ShippingMethod[]): string {
    const pickup = methods.find(
        (method) => isPickup(method) && method.fee_cents === 0,
    );
    const deliveries = methods.filter((method) => !isPickup(method));

    if (!pickup) {
        return 'Choose how to get it at checkout.';
    }

    if (deliveries.length === 0) {
        return 'Pick it up for free.';
    }

    const fee = (method: ShippingMethod): string =>
        method.fee_cents === 0 ? 'free' : method.fee;

    if (deliveries.length === 1) {
        const [only] = deliveries;

        return `Pick it up for free, or have ${only.name} deliver it for ${fee(only)}.`;
    }

    const list = deliveries
        .map((method) => `${method.name} for ${fee(method)}`)
        .join(', or ');

    return `Pick it up for free, or have it delivered: ${list}.`;
}
