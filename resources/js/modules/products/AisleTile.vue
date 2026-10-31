<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';
import { index as productsIndex } from '@/routes/products';
import type { Aisle } from '@/types';

// One aisle on the home page, linking to the shop filtered to it. `tint` is
// the aisle's position in the list modulo 3, plus 1. The photo sits top right
// (on a `well` backing in dark mode); with no photo, a basket on `well`.
const props = defineProps<{
    aisle: Aisle;
    tint: 1 | 2 | 3;
}>();

const tints: Record<1 | 2 | 3, string> = {
    1: 'bg-tint-1',
    2: 'bg-tint-2',
    3: 'bg-tint-3',
};
</script>

<template>
    <Link
        :href="productsIndex.url({ query: { aisle: [props.aisle.slug] } })"
        class="group relative flex min-h-[220px] flex-col justify-between gap-4 rounded-[24px] border-[2.5px] border-outline p-6 text-ink shadow-[5px_5px_0_var(--shadow-ink)]"
        :class="tints[props.tint]"
    >
        <div class="flex justify-end">
            <img
                v-if="props.aisle.image_url"
                :src="props.aisle.image_url"
                alt=""
                loading="lazy"
                class="h-[130px] w-auto max-w-[70%] object-contain mix-blend-multiply dark:rounded-2xl dark:bg-well dark:p-2.5 dark:mix-blend-normal"
            />
            <span
                v-else
                data-test="aisle-tile-fallback"
                class="flex h-[130px] w-[130px] items-center justify-center rounded-2xl bg-well text-on-marigold"
            >
                <Icon name="basket" :size="56" />
            </span>
        </div>

        <div class="flex items-end justify-between gap-4">
            <div class="min-w-0">
                <h3
                    class="font-serif text-[26px] leading-[1.1] font-normal wrap-anywhere sm:text-[30px]"
                >
                    {{ props.aisle.name }}
                </h3>
                <p class="mt-1 text-[15px] font-semibold text-body">
                    {{ props.aisle.product_count }}
                    {{
                        props.aisle.product_count === 1 ? 'product' : 'products'
                    }}
                </p>
            </div>
            <span
                aria-hidden="true"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-outline text-paper transition-colors duration-200 ease-stall group-hover:bg-marigold group-hover:text-on-marigold"
            >
                <Icon name="chevron-right" :size="22" />
            </span>
        </div>
    </Link>
</template>
