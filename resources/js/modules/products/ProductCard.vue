<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import PriceTag from '@/common/stall/PriceTag.vue';
import QuantityBasketBar from '@/common/stall/QuantityBasketBar.vue';
import { addToCart } from '@/modules/cart/useCartActions';
import StarRating from '@/modules/products/StarRating.vue';
import { show } from '@/routes/products';
import type { Product } from '@/types';

// A product on the counter: the photo on `well` with its price tag, the name,
// the rating line and, on desktop, the joined quantity-and-basket bar. Below
// 640px (the store's phone breakpoint) the card is narrower and shows the
// full-width "Add" button instead.
const props = defineProps<{
    product: Product;
}>();

const isPhone = useMediaQuery('(max-width: 639.98px)');
const quantity = ref(1);

function add(): void {
    addToCart(props.product.id, quantity.value);
}
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-[26px] border-[1.5px] border-line bg-surface"
    >
        <Link
            :href="show(props.product)"
            tabindex="-1"
            aria-hidden="true"
            class="relative flex h-[130px] items-center justify-center bg-well p-3 sm:h-[270px] sm:p-5"
        >
            <img
                v-if="props.product.image_url"
                :src="props.product.image_url"
                alt=""
                loading="lazy"
                class="max-h-[106px] max-w-full object-contain mix-blend-multiply sm:max-h-[228px]"
            />
            <Icon
                v-else
                name="basket"
                :size="48"
                class="text-on-marigold opacity-30"
            />
            <span class="absolute top-2.5 left-2.5 sm:top-4 sm:left-4">
                <PriceTag :price="props.product.price" />
            </span>
        </Link>

        <div class="flex flex-1 flex-col gap-2 p-3 sm:gap-3 sm:p-5">
            <h3
                class="font-serif text-[19px] leading-[1.2] font-normal wrap-anywhere text-ink sm:text-[25px]"
            >
                <Link
                    :href="show(props.product)"
                    class="rounded-md underline-offset-4 hover:underline"
                >
                    {{ props.product.name }}
                </Link>
            </h3>
            <StarRating
                :average="props.product.rating_average"
                :count="props.product.rating_count"
            />
            <div class="mt-auto pt-1">
                <QuantityBasketBar
                    v-model="quantity"
                    :product-name="props.product.name"
                    :compact="isPhone"
                    @add="add"
                />
            </div>
        </div>
    </article>
</template>
