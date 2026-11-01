<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import ActiveFilterChips from '@/modules/products/ActiveFilterChips.vue';
import { hasPriceFilter } from '@/modules/products/shopFilters';
import type { ShopFilters } from '@/modules/products/shopFilters';
import { index as productsIndex } from '@/routes/products';
import type { Aisle } from '@/types';

// What the shop shows when nothing matches: the filters in force as removable
// chips, a dashed card with a sad magnifier and tips (the price tip only when
// a price is set), and a chip for each aisle to start again from.
const props = defineProps<{
    filters: ShopFilters;
    aisles: Aisle[];
}>();

const tips = computed(() => [
    'Check the spelling, or try a shorter word.',
    ...(hasPriceFilter(props.filters)
        ? ['Remove the price filter to see more.']
        : []),
]);

const tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3'] as const;
</script>

<template>
    <div class="flex flex-col gap-6">
        <ActiveFilterChips :filters="props.filters" :aisles="props.aisles" />

        <section
            aria-labelledby="no-results-heading"
            class="flex flex-col items-center gap-5 rounded-[28px] border-[2.5px] border-dashed border-line-strong bg-surface px-5 py-9 text-center sm:px-8 sm:py-11"
        >
            <span
                class="flex size-[120px] items-center justify-center rounded-full bg-cream text-ink"
            >
                <Icon name="search-sad" :size="64" />
            </span>
            <h2
                id="no-results-heading"
                class="font-serif text-[26px] leading-tight font-normal text-ink sm:text-[32px]"
            >
                No products match that
            </h2>
            <ul
                class="flex max-w-[460px] list-disc flex-col gap-1.5 pl-5 text-left text-base leading-[1.6] text-body sm:text-[17px]"
            >
                <li v-for="tip in tips" :key="tip">{{ tip }}</li>
            </ul>

            <div
                v-if="props.aisles.length > 0"
                class="flex flex-col items-center gap-3"
            >
                <p
                    id="no-results-aisles"
                    class="text-[15px] font-bold text-ink"
                >
                    Or browse an aisle:
                </p>
                <ul
                    aria-labelledby="no-results-aisles"
                    class="flex flex-wrap justify-center gap-2"
                >
                    <li v-for="(aisle, index) in props.aisles" :key="aisle.id">
                        <Link
                            :href="
                                productsIndex.url({
                                    query: { aisle: [aisle.slug] },
                                })
                            "
                            class="inline-flex min-h-11 items-center rounded-full border-2 border-outline px-4 text-[15px] font-bold text-ink transition-colors duration-200 ease-stall hover:bg-field"
                            :class="tints[index % tints.length]"
                        >
                            {{ aisle.name }}
                        </Link>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
