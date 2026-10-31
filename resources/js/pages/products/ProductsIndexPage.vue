<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import Pagination from '@/common/pagination/Pagination.vue';
import Select from '@/common/select/Select.vue';
import FeaturedCarousel from '@/modules/products/FeaturedCarousel.vue';
import FilterSheet from '@/modules/products/FilterSheet.vue';
import NoResults from '@/modules/products/NoResults.vue';
import ProductCard from '@/modules/products/ProductCard.vue';
import ProductFilters from '@/modules/products/ProductFilters.vue';
import ToggleChip from '@/modules/products/ToggleChip.vue';
import {
    DEFAULT_SORT,
    normaliseFilters,
    productCount,
    SORT_OPTIONS,
    visitShop,
} from '@/modules/products/shopFilters';
import type { Aisle, Paginated, Product, ProductFilterValues } from '@/types';

// The shop. From 640px the filters sit in a sidebar (stacked above the grid
// below 980px); on phones a Filters button opens them in a bottom sheet, with
// the sort beside it and a swipeable row of aisle chips underneath. Below the
// grid: pagination, then the featured carousel. With no matches the grid
// gives way to NoResults.
const props = defineProps<{
    products: Paginated<Product>;
    filters: ProductFilterValues;
    aisles: Aisle[];
    featured: Product[];
}>();

const isPhone = useMediaQuery('(max-width: 639.98px)');
const sheetOpen = ref(false);

watch(isPhone, () => {
    sheetOpen.value = false;
});

const filters = computed(() => normaliseFilters(props.filters));
const total = computed(() => props.products.meta.total);

const heading = computed(() =>
    filters.value.q === ''
        ? 'All products'
        : `Results for "${filters.value.q}"`,
);

const tints = ['tint-1', 'tint-2', 'tint-3'] as const;

// The phone's aisle row picks one aisle at a time; "All" clears it.
function pickAisle(slug: string | null): void {
    visitShop({ ...filters.value, aisle: slug === null ? [] : [slug] });
}

function isOnlyAisle(slug: string): boolean {
    return filters.value.aisle.length === 1 && filters.value.aisle[0] === slug;
}
</script>

<template>
    <Head title="Shop" />

    <div class="flex flex-col gap-5 pt-1 pb-6 sm:gap-8 sm:pt-4 lg:pb-12">
        <div
            class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1"
        >
            <h1
                class="min-w-0 font-serif text-[34px] leading-[1.1] font-normal tracking-[-0.5px] wrap-anywhere text-ink lg:text-[52px] lg:tracking-[-1px]"
            >
                {{ heading }}
            </h1>
            <p
                class="text-[15px] font-semibold text-subtle tabular-nums"
                aria-live="polite"
            >
                {{ productCount(total) }}
            </p>
        </div>

        <template v-if="isPhone">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex min-h-12 shrink-0 items-center gap-2 rounded-xl border-[2.5px] border-outline bg-surface px-4 text-base font-extrabold text-ink shadow-[2px_2px_0_var(--shadow-ink)] transition-colors duration-200 ease-stall hover:bg-field"
                    aria-haspopup="dialog"
                    @click="sheetOpen = true"
                >
                    <Icon name="filters" :size="20" />
                    Filters
                </button>
                <label for="phone-sort" class="sr-only">Sort by</label>
                <Select
                    id="phone-sort"
                    class="w-auto min-w-0 flex-1"
                    :model-value="filters.sort"
                    :options="SORT_OPTIONS"
                    @update:model-value="
                        visitShop({ ...filters, sort: $event ?? DEFAULT_SORT })
                    "
                />
            </div>

            <nav
                v-if="props.aisles.length > 0"
                aria-label="Aisles"
                class="-mx-4 [scrollbar-width:none] overflow-x-auto px-4 [&::-webkit-scrollbar]:hidden"
            >
                <ul class="flex w-max gap-2 py-1">
                    <li>
                        <ToggleChip
                            :pressed="filters.aisle.length === 0"
                            @click="pickAisle(null)"
                        >
                            All
                        </ToggleChip>
                    </li>
                    <li v-for="(aisle, index) in props.aisles" :key="aisle.id">
                        <ToggleChip
                            :pressed="isOnlyAisle(aisle.slug)"
                            :tone="tints[index % tints.length]"
                            @click="pickAisle(aisle.slug)"
                        >
                            {{ aisle.name }}
                        </ToggleChip>
                    </li>
                </ul>
            </nav>

            <FilterSheet
                :open="sheetOpen"
                :filters="filters"
                :total="total"
                @close="sheetOpen = false"
            />
        </template>

        <div class="flex flex-col gap-8 lg:flex-row">
            <aside v-if="!isPhone" class="min-w-0 lg:flex-[1_1_250px]">
                <ProductFilters :filters="filters" :aisles="props.aisles" />
            </aside>

            <section
                class="flex min-w-0 flex-col gap-8 lg:flex-[999_1_560px]"
                aria-label="Products"
            >
                <template v-if="props.products.data.length > 0">
                    <ul
                        class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fill,minmax(250px,1fr))] sm:gap-6"
                    >
                        <li
                            v-for="product in props.products.data"
                            :key="product.id"
                            class="flex min-w-0 flex-col"
                        >
                            <ProductCard :product="product" class="flex-1" />
                        </li>
                    </ul>
                    <Pagination :links="props.products.meta.links" />
                </template>
                <NoResults v-else :filters="filters" :aisles="props.aisles" />
            </section>
        </div>

        <!-- The hanging sign rises about 58px above the carousel panel, so
             the margin leaves the mockup's 56px clear above the sign. -->
        <div v-if="props.featured.length > 0" class="mt-14 sm:mt-20">
            <FeaturedCarousel :products="props.featured" />
        </div>
    </div>
</template>
