<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import Input from '@/common/input/Input.vue';
import Select from '@/common/select/Select.vue';
import ToggleChip from '@/modules/products/ToggleChip.vue';
import {
    clearedFilters,
    DEFAULT_SORT,
    isShortcut,
    PRICE_SHORTCUTS,
    RATING_STEPS,
    SORT_OPTIONS,
    visitShop,
    withShortcut,
} from '@/modules/products/shopFilters';
import type {
    PriceShortcut,
    ShopFilters,
} from '@/modules/products/shopFilters';
import type { Aisle } from '@/types';

// The shop's sidebar filters (640px and up): search, aisles with counts, price
// shortcuts and bounds, minimum rating, rated and featured only, the sort and
// "Clear filters". Every change visits the shop at once (the search after a
// short pause). The boxes and radios mirror `filters`, which the server sends
// back with each visit; the text fields keep what you're typing while focused.
const props = defineProps<{
    filters: ShopFilters;
    aisles: Aisle[];
}>();

const SEARCH_DEBOUNCE_MS = 300;

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const priceErrors = computed(() =>
    [errors.value.min_price, errors.value.max_price].filter(Boolean),
);
const aisleError = computed(
    () => errors.value.aisle ?? errors.value['aisle.0'],
);

const q = ref(props.filters.q);
const minPrice = ref(props.filters.min_price);
const maxPrice = ref(props.filters.max_price);

function isFocused(id: string): boolean {
    return typeof document !== 'undefined' && document.activeElement?.id === id;
}

watch(
    () => props.filters,
    (filters) => {
        if (!isFocused('filter-q')) {
            q.value = filters.q;
        }

        if (!isFocused('filter-min-price')) {
            minPrice.value = filters.min_price;
        }

        if (!isFocused('filter-max-price')) {
            maxPrice.value = filters.max_price;
        }
    },
);

// The filters with whatever is in the text fields right now.
const current = computed<ShopFilters>(() => ({
    ...props.filters,
    q: q.value,
    min_price: minPrice.value,
    max_price: maxPrice.value,
}));

function update(patch: Partial<ShopFilters> = {}): void {
    visitShop({ ...current.value, ...patch });
}

const searchDebounced = useDebounceFn(() => update(), SEARCH_DEBOUNCE_MS);

function checked(event: Event): boolean {
    return (event.target as HTMLInputElement).checked;
}

function toggleAisle(slug: string, event: Event): void {
    const others = props.filters.aisle.filter((other) => other !== slug);

    update({ aisle: checked(event) ? [...others, slug] : others });
}

function pickShortcut(shortcut: PriceShortcut): void {
    const next = withShortcut(current.value, shortcut);

    minPrice.value = next.min_price;
    maxPrice.value = next.max_price;
    update();
}

function clear(): void {
    q.value = '';
    minPrice.value = '';
    maxPrice.value = '';
    visitShop(clearedFilters(props.filters));
}

const label = 'text-[15px] font-bold text-ink';
const option =
    'flex min-h-11 cursor-pointer items-center gap-3 text-base font-semibold text-ink';
const control = 'size-5 shrink-0 cursor-pointer accent-green dark:scheme-dark';
const error = 'text-[14px] font-semibold text-danger';
const field = 'h-[46px] border-[1.5px]';
</script>

<template>
    <form
        class="flex flex-col gap-5 rounded-[22px] border-[2.5px] border-outline bg-surface p-6 shadow-card"
        aria-labelledby="filters-heading"
        @submit.prevent="update()"
    >
        <h2
            id="filters-heading"
            class="font-serif text-[26px] leading-tight font-normal text-ink"
        >
            Filters
        </h2>

        <div class="grid gap-2">
            <label for="filter-q" :class="label">Search</label>
            <Input
                id="filter-q"
                v-model="q"
                type="search"
                placeholder="Apple, water…"
                autocomplete="off"
                :class="field"
                :aria-invalid="errors.q ? true : undefined"
                @update:model-value="searchDebounced"
            />
            <p v-if="errors.q" :class="error">{{ errors.q }}</p>
        </div>

        <fieldset v-if="props.aisles.length > 0" class="grid">
            <legend :class="[label, 'mb-1.5']">Aisle</legend>
            <label
                v-for="aisle in props.aisles"
                :key="aisle.id"
                :class="option"
            >
                <input
                    type="checkbox"
                    name="aisle[]"
                    :value="aisle.slug"
                    :checked="props.filters.aisle.includes(aisle.slug)"
                    :class="control"
                    @change="toggleAisle(aisle.slug, $event)"
                />
                <span class="min-w-0 flex-1 wrap-anywhere">{{
                    aisle.name
                }}</span>
                <span
                    class="rounded-full bg-paper px-2.5 py-0.5 text-[14px] font-bold text-subtle tabular-nums"
                >
                    <span class="sr-only">(</span>{{ aisle.product_count
                    }}<span class="sr-only">)</span>
                </span>
            </label>
            <p v-if="aisleError" :class="error">{{ aisleError }}</p>
        </fieldset>

        <fieldset class="grid gap-3">
            <legend :class="[label, 'mb-2']">Price</legend>
            <div class="flex flex-wrap gap-2">
                <ToggleChip
                    v-for="shortcut in PRICE_SHORTCUTS"
                    :key="shortcut.label"
                    :pressed="isShortcut(current, shortcut)"
                    @click="pickShortcut(shortcut)"
                >
                    {{ shortcut.label }}
                </ToggleChip>
            </div>
            <div class="flex items-center gap-2">
                <Input
                    id="filter-min-price"
                    v-model="minPrice"
                    inputmode="decimal"
                    placeholder="$ min"
                    aria-label="Minimum price in dollars"
                    :class="[field, 'min-w-0 flex-1']"
                    :aria-invalid="errors.min_price ? true : undefined"
                    @change="update()"
                />
                <span class="text-[15px] font-semibold text-subtle">to</span>
                <Input
                    id="filter-max-price"
                    v-model="maxPrice"
                    inputmode="decimal"
                    placeholder="$ max"
                    aria-label="Maximum price in dollars"
                    :class="[field, 'min-w-0 flex-1']"
                    :aria-invalid="errors.max_price ? true : undefined"
                    @change="update()"
                />
            </div>
            <p v-for="message in priceErrors" :key="message" :class="error">
                {{ message }}
            </p>
        </fieldset>

        <fieldset class="grid">
            <legend :class="[label, 'mb-1.5']">Minimum rating</legend>
            <label :class="option">
                <input
                    type="radio"
                    name="rating"
                    value=""
                    :checked="props.filters.rating === ''"
                    :class="control"
                    @change="update({ rating: '' })"
                />
                Any rating
            </label>
            <label v-for="stars in RATING_STEPS" :key="stars" :class="option">
                <input
                    type="radio"
                    name="rating"
                    :value="String(stars)"
                    :checked="props.filters.rating === String(stars)"
                    :class="control"
                    @change="update({ rating: String(stars) })"
                />
                <span>
                    <span aria-hidden="true" class="mr-1 text-star">{{
                        '★'.repeat(stars)
                    }}</span>
                    {{ stars }}
                    <span class="sr-only">{{
                        stars === 1 ? 'star' : 'stars'
                    }}</span>
                    and up
                </span>
            </label>
            <div class="my-2 border-t-[1.5px] border-line" />
            <label :class="option">
                <input
                    type="checkbox"
                    name="rated"
                    :checked="props.filters.rated"
                    :class="control"
                    @change="update({ rated: checked($event) })"
                />
                Only rated products
            </label>
            <p v-if="errors.rating" :class="error">{{ errors.rating }}</p>
        </fieldset>

        <fieldset class="grid">
            <legend :class="[label, 'mb-1.5']">Show</legend>
            <label :class="option">
                <input
                    type="checkbox"
                    name="featured"
                    :checked="props.filters.featured"
                    :class="control"
                    @change="update({ featured: checked($event) })"
                />
                Featured products only
            </label>
        </fieldset>

        <div class="grid gap-2">
            <label for="filter-sort" :class="label">Sort by</label>
            <Select
                id="filter-sort"
                :class="[field, 'w-full data-[size=default]:h-[46px]']"
                :model-value="props.filters.sort"
                :options="SORT_OPTIONS"
                @update:model-value="update({ sort: $event ?? DEFAULT_SORT })"
            />
            <p v-if="errors.sort" :class="error">{{ errors.sort }}</p>
        </div>

        <button
            type="button"
            class="min-h-12 w-full rounded-xl border-2 border-link bg-surface px-5 text-base font-extrabold text-link transition-colors duration-200 ease-stall hover:bg-field"
            @click="clear"
        >
            Clear filters
        </button>
    </form>
</template>
