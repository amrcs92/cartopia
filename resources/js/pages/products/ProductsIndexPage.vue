<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Pagination from '@/common/pagination/Pagination.vue';
import ProductCard from '@/modules/products/ProductCard.vue';
import ProductFilters from '@/modules/products/ProductFilters.vue';
import { index as productsIndex } from '@/routes/products';
import type { Paginated, Product, ProductFilterValues } from '@/types';

const props = defineProps<{
    products: Paginated<Product>;
    filters: ProductFilterValues;
}>();

function applyFilters(params: Record<string, string>): void {
    router.get(productsIndex(), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['products', 'filters'],
    });
}
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col gap-8 lg:flex-row">
        <aside class="w-full shrink-0 lg:w-64">
            <h2 class="mb-4 text-lg font-semibold">Filters</h2>
            <ProductFilters :filters="props.filters" @change="applyFilters" />
        </aside>

        <section class="flex-1 space-y-6">
            <div class="flex items-baseline justify-between gap-4">
                <h1 class="text-2xl font-semibold">Products</h1>
                <p class="text-sm text-muted-foreground" aria-live="polite">
                    {{ props.products.meta.total }}
                    {{ props.products.meta.total === 1 ? 'result' : 'results' }}
                </p>
            </div>

            <div
                v-if="props.products.data.length > 0"
                class="grid grid-cols-2 gap-4 md:grid-cols-3"
            >
                <ProductCard
                    v-for="product in props.products.data"
                    :key="product.id"
                    :product="product"
                />
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                No products match your filters.
            </div>

            <Pagination :links="props.products.meta.links" />
        </section>
    </div>
</template>
