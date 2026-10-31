<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import Button from '@/common/button/Button.vue';
import InputError from '@/common/app/InputError.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import Select from '@/common/select/Select.vue';
import type { ProductFilterValues, SelectOption } from '@/types';

const SEARCH_DEBOUNCE_MS = 300;
const ANY_RATING = 'any';
const DEFAULT_SORT = 'newest';

const props = defineProps<{
    filters: ProductFilterValues;
}>();

const emit = defineEmits<{
    change: [params: Record<string, string>];
}>();

const page = usePage();
const errors = computed(() => page.props.errors);

const form = reactive({
    q: props.filters.q ?? '',
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
    rating: props.filters.rating ?? ANY_RATING,
    sort: props.filters.sort ?? DEFAULT_SORT,
});

const ratingOptions: SelectOption[] = [
    { value: ANY_RATING, label: 'Any rating' },
    { value: '4', label: '4 stars & up' },
    { value: '3', label: '3 stars & up' },
    { value: '2', label: '2 stars & up' },
    { value: '1', label: '1 star & up' },
];

const sortOptions: SelectOption[] = [
    { value: 'newest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'rating', label: 'Top rated' },
];

// Only non-default values go into the query string, and changing any filter
// starts again from page 1.
function params(): Record<string, string> {
    const result: Record<string, string> = {};
    const q = form.q.trim();

    if (q !== '') {
        result.q = q;
    }

    if (form.min_price.trim() !== '') {
        result.min_price = form.min_price.trim();
    }

    if (form.max_price.trim() !== '') {
        result.max_price = form.max_price.trim();
    }

    if (form.rating !== ANY_RATING) {
        result.rating = form.rating;
    }

    if (form.sort !== DEFAULT_SORT) {
        result.sort = form.sort;
    }

    return result;
}

function apply(): void {
    emit('change', params());
}

const applyDebounced = useDebounceFn(apply, SEARCH_DEBOUNCE_MS);

function onRating(value: string | undefined): void {
    form.rating = value ?? ANY_RATING;
    apply();
}

function onSort(value: string | undefined): void {
    form.sort = value ?? DEFAULT_SORT;
    apply();
}

function reset(): void {
    form.q = '';
    form.min_price = '';
    form.max_price = '';
    form.rating = ANY_RATING;
    form.sort = DEFAULT_SORT;
    apply();
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="apply">
        <div class="grid gap-2">
            <Label for="filter-q">Search</Label>
            <Input
                id="filter-q"
                v-model="form.q"
                type="search"
                placeholder="Search products"
                autocomplete="off"
                @update:model-value="applyDebounced"
            />
            <InputError :message="errors.q" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm leading-none font-medium">
                Price ($)
            </legend>
            <div class="flex items-center gap-2">
                <Input
                    id="filter-min-price"
                    v-model="form.min_price"
                    inputmode="decimal"
                    placeholder="Min"
                    aria-label="Minimum price"
                    @change="apply"
                />
                <span class="text-muted-foreground">to</span>
                <Input
                    id="filter-max-price"
                    v-model="form.max_price"
                    inputmode="decimal"
                    placeholder="Max"
                    aria-label="Maximum price"
                    @change="apply"
                />
            </div>
            <InputError :message="errors.min_price" />
            <InputError :message="errors.max_price" />
        </fieldset>

        <div class="grid gap-2">
            <Label for="filter-rating">Minimum rating</Label>
            <Select
                id="filter-rating"
                class="w-full"
                :model-value="form.rating"
                :options="ratingOptions"
                @update:model-value="onRating"
            />
            <InputError :message="errors.rating" />
        </div>

        <div class="grid gap-2">
            <Label for="filter-sort">Sort by</Label>
            <Select
                id="filter-sort"
                class="w-full"
                :model-value="form.sort"
                :options="sortOptions"
                @update:model-value="onSort"
            />
            <InputError :message="errors.sort" />
        </div>

        <Button type="button" variant="outline" @click="reset">
            Clear filters
        </Button>
    </form>
</template>
