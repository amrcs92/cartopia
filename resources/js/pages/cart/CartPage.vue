<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';
import StallButton from '@/common/stall/StallButton.vue';
import CartLineItem from '@/modules/cart/CartLineItem.vue';
import CartSummary from '@/modules/cart/CartSummary.vue';
import CheckoutPanel from '@/modules/cart/CheckoutPanel.vue';
import FeaturedCarousel from '@/modules/products/FeaturedCarousel.vue';
import { index as productsIndex } from '@/routes/products';
import type { Cart, Product, ShippingMethod } from '@/types';

// The basket: line items beside an order summary with the shipping choice,
// "Pay with" and the total. With nothing in it, a way back to the shop and
// the featured carousel. On phones the checkout bar is pinned to the bottom,
// so the page leaves room for it.
const props = defineProps<{
    cart: Cart;
    shippingMethods: ShippingMethod[];
    featured: Product[];
}>();
</script>

<template>
    <Head title="Your basket" />

    <div class="space-y-8">
        <div
            v-if="props.cart.lines.length === 0"
            class="flex flex-col items-center gap-10 pt-4"
            data-test="empty-cart"
        >
            <div class="flex flex-col items-center gap-5 text-center">
                <span
                    aria-hidden="true"
                    class="inline-flex size-28 items-center justify-center rounded-full border-[2.5px] border-outline bg-field text-ink"
                >
                    <Icon name="basket" :size="56" />
                </span>
                <h1 class="font-serif text-[32px] leading-tight text-ink">
                    Your basket is empty
                </h1>
                <p class="text-body">
                    Nothing in here yet. Have a look at what is in the shop.
                </p>
                <StallButton
                    as="link"
                    variant="marigold"
                    size="lg"
                    :href="productsIndex().url"
                >
                    Browse the shop
                </StallButton>
            </div>
            <div class="w-full pt-6">
                <FeaturedCarousel :products="props.featured" />
            </div>
        </div>

        <div v-else class="space-y-6">
            <h1
                class="font-serif text-[32px] leading-tight text-ink lg:text-5xl"
            >
                Your basket
            </h1>

            <div class="grid items-start gap-8 lg:grid-cols-[1fr_24rem]">
                <ul class="flex flex-col gap-4">
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
    </div>
</template>
