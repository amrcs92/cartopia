<script setup lang="ts">
import type { Cart } from '@/types';

// The order summary card: the items and subtotal; the slot holds the shipping
// choice, "Pay with" and the total.
const props = defineProps<{
    cart: Cart;
}>();
</script>

<template>
    <section
        aria-labelledby="order-summary"
        class="flex flex-col gap-5 rounded-[26px] border-[2.5px] border-outline bg-surface p-5 shadow-card lg:sticky lg:top-6 lg:p-6"
    >
        <h2 id="order-summary" class="font-serif text-[26px] text-ink">
            Order summary
        </h2>
        <dl class="space-y-2">
            <div class="flex justify-between text-body">
                <dt>Items</dt>
                <dd class="tabular-nums" data-test="cart-total-quantity">
                    {{ props.cart.total_quantity }}
                </dd>
            </div>
            <div class="flex justify-between font-bold text-ink">
                <dt>Subtotal</dt>
                <dd class="tabular-nums" data-test="cart-subtotal">
                    {{ props.cart.subtotal }}
                </dd>
            </div>
        </dl>
        <slot />
    </section>
</template>
