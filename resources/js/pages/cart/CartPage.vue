<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/common/button/Button.vue';
import CartLineItem from '@/modules/cart/CartLineItem.vue';
import CartSummary from '@/modules/cart/CartSummary.vue';
import CheckoutPanel from '@/modules/cart/CheckoutPanel.vue';
import { index as productsIndex } from '@/routes/products';
import type { Cart, ShippingMethod } from '@/types';

const props = defineProps<{
    cart: Cart;
    shippingMethods: ShippingMethod[];
}>();
</script>

<template>
    <Head title="Your cart" />

    <div class="space-y-6">
        <h1 class="text-2xl font-semibold">Your cart</h1>

        <div
            v-if="props.cart.lines.length === 0"
            class="space-y-4 rounded-xl border border-dashed p-10 text-center"
            data-test="empty-cart"
        >
            <p class="text-muted-foreground">Your cart is empty.</p>
            <Button as-child>
                <Link :href="productsIndex()">Continue shopping</Link>
            </Button>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-[1fr_20rem]">
            <ul class="divide-y rounded-xl border px-4">
                <CartLineItem
                    v-for="line in props.cart.lines"
                    :key="line.product.id"
                    :line="line"
                />
            </ul>

            <CartSummary :cart="props.cart">
                <CheckoutPanel
                    :cart="props.cart"
                    :shipping-methods="props.shippingMethods"
                />
            </CartSummary>
        </div>
    </div>
</template>
