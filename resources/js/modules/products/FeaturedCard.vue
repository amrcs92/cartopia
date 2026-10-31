<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import PriceTag from '@/common/stall/PriceTag.vue';
import QuantityBasketBar from '@/common/stall/QuantityBasketBar.vue';
import { addToCart } from '@/modules/cart/useCartActions';
import StarRating from '@/modules/products/StarRating.vue';
import { show } from '@/routes/products';
import type { Product } from '@/types';

// One card in the featured carousel: the photo on the product's tint (on
// `well` in dark mode) with the "Featured" ribbon and a price tag, then the
// aisle, the name, the rating line and the quantity-and-basket bar.
const props = defineProps<{
    product: Product;
    tint: 1 | 2 | 3;
}>();

const tints: Record<1 | 2 | 3, string> = {
    1: 'bg-tint-1',
    2: 'bg-tint-2',
    3: 'bg-tint-3',
};

const quantity = ref(1);

function add(): void {
    addToCart(props.product.id, quantity.value);
}
</script>

<template>
    <article
        class="flex h-full flex-col overflow-hidden rounded-[22px] border-[2.5px] border-outline bg-surface"
    >
        <Link
            :href="show(props.product)"
            tabindex="-1"
            aria-hidden="true"
            class="relative flex h-[180px] items-center justify-center overflow-hidden p-5 sm:h-[210px] dark:bg-well"
            :class="tints[props.tint]"
        >
            <img
                v-if="props.product.image_url"
                :src="props.product.image_url"
                alt=""
                loading="lazy"
                class="max-h-full max-w-full object-contain mix-blend-multiply"
            />
            <Icon
                v-else
                name="basket"
                :size="56"
                class="text-on-marigold opacity-30"
            />
            <span
                class="absolute top-[18px] -left-[38px] w-[140px] -rotate-45 bg-green py-1 text-center text-xs font-extrabold tracking-wide text-white"
            >
                Featured
            </span>
            <span class="absolute right-3 bottom-3">
                <PriceTag :price="props.product.price" />
            </span>
        </Link>

        <div class="flex flex-1 flex-col gap-2 p-4 sm:p-5">
            <p
                v-if="props.product.aisle"
                class="text-[13px] font-bold text-subtle"
            >
                {{ props.product.aisle }}
            </p>
            <h3
                class="font-serif text-[20px] leading-[1.2] font-normal wrap-anywhere text-ink sm:text-[24px]"
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
            <div class="mt-auto pt-2">
                <QuantityBasketBar
                    v-model="quantity"
                    :product-name="props.product.name"
                    @add="add"
                />
            </div>
        </div>
    </article>
</template>
