<script setup lang="ts">
import { computed } from 'vue';
import Chip from '@/common/stall/Chip.vue';
import {
    clearedFilters,
    isShortcut,
    PRICE_SHORTCUTS,
    ratingLabel,
    visitShop,
} from '@/modules/products/shopFilters';
import type { ShopFilters } from '@/modules/products/shopFilters';
import type { Aisle } from '@/types';

// The filters in force, as removable chips ("Search: mango", "Under $1",
// "Drinks"), then "Clear all". Removing a chip visits the shop without that
// filter; the sort order is kept either way.
const props = defineProps<{
    filters: ShopFilters;
    aisles: Aisle[];
}>();

type ActiveFilter = {
    key: string;
    label: string;
    without: Partial<ShopFilters>;
};

function priceLabel(min: string, max: string): string {
    const shortcut = PRICE_SHORTCUTS.find((each) =>
        isShortcut({ ...props.filters, min_price: min, max_price: max }, each),
    );

    if (shortcut) {
        return shortcut.label;
    }

    if (min !== '' && max !== '') {
        return `$${min} to $${max}`;
    }

    return min !== '' ? `From $${min}` : `Up to $${max}`;
}

const active = computed<ActiveFilter[]>(() => {
    const { q, aisle, min_price, max_price, rating, rated, featured } =
        props.filters;
    const list: ActiveFilter[] = [];

    if (q !== '') {
        list.push({ key: 'q', label: `Search: ${q}`, without: { q: '' } });
    }

    if (min_price !== '' || max_price !== '') {
        list.push({
            key: 'price',
            label: priceLabel(min_price, max_price),
            without: { min_price: '', max_price: '' },
        });
    }

    for (const slug of aisle) {
        list.push({
            key: `aisle-${slug}`,
            label:
                props.aisles.find((each) => each.slug === slug)?.name ?? slug,
            without: { aisle: aisle.filter((other) => other !== slug) },
        });
    }

    if (rating !== '') {
        list.push({
            key: 'rating',
            label: ratingLabel(Number(rating)),
            without: { rating: '' },
        });
    }

    if (rated) {
        list.push({
            key: 'rated',
            label: 'Only rated products',
            without: { rated: false },
        });
    }

    if (featured) {
        list.push({
            key: 'featured',
            label: 'Featured products only',
            without: { featured: false },
        });
    }

    return list;
});

function remove(filter: ActiveFilter): void {
    visitShop({ ...props.filters, ...filter.without });
}
</script>

<template>
    <div
        v-if="active.length > 0"
        class="flex flex-wrap items-center gap-x-2 gap-y-3"
    >
        <h2 class="sr-only">Active filters</h2>
        <ul class="contents">
            <li v-for="filter in active" :key="filter.key" class="max-w-full">
                <Chip removable @remove="remove(filter)">
                    {{ filter.label }}
                </Chip>
            </li>
        </ul>
        <button
            type="button"
            class="min-h-11 rounded-lg px-2 text-[15px] font-bold text-link underline underline-offset-4 transition-colors duration-200 ease-stall hover:bg-field"
            @click="visitShop(clearedFilters(props.filters))"
        >
            Clear all
        </button>
    </div>
</template>
