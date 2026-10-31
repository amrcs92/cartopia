import { router } from '@inertiajs/vue3';
import { destroy, store, update } from '@/routes/cart/items';

export const MIN_QUANTITY = 1;
export const MAX_QUANTITY = 99;

const options = {
    preserveScroll: true,
    only: ['cart', 'flash'],
};

export function clampQuantity(value: number): number {
    if (!Number.isFinite(value)) {
        return MIN_QUANTITY;
    }

    return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, Math.trunc(value)));
}

export function addToCart(productId: number, quantity = 1): void {
    router.post(
        store(),
        { product_id: productId, quantity: clampQuantity(quantity) },
        options,
    );
}

export function updateCartItem(productId: number, quantity: number): void {
    router.patch(
        update(productId),
        { quantity: clampQuantity(quantity) },
        options,
    );
}

export function removeCartItem(productId: number): void {
    router.delete(destroy(productId), options);
}
