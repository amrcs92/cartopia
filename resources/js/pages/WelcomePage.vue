<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Button from '@/common/button/Button.vue';
import ProductCard from '@/modules/products/ProductCard.vue';
import { index as productsIndex } from '@/routes/products';
import type { Product } from '@/types';

const props = defineProps<{
    featured: Product[];
}>();
</script>

<template>
    <Head title="Welcome" />

    <section
        class="flex flex-col items-center gap-6 rounded-xl border bg-muted/40 px-6 py-12 text-center sm:py-16"
    >
        <img
            src="/images/cartopia_logo.png"
            alt="Cartopia"
            class="h-16 w-auto sm:h-20"
        />
        <div class="max-w-2xl space-y-3">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                Everyday essentials, delivered.
            </h1>
            <p class="text-muted-foreground">
                Browse the catalog, fill your cart and pay from your Cartopia
                wallet.
            </p>
        </div>
        <Button size="lg" as-child>
            <Link :href="productsIndex()">
                Shop all products
                <ArrowRight />
            </Link>
        </Button>
    </section>

    <section class="mt-10 space-y-4">
        <div class="flex items-baseline justify-between gap-4">
            <h2 class="text-xl font-semibold">New arrivals</h2>
            <Link
                :href="productsIndex()"
                class="text-sm text-muted-foreground hover:text-foreground hover:underline"
            >
                View all
            </Link>
        </div>

        <div
            v-if="props.featured.length > 0"
            class="grid grid-cols-2 gap-4 md:grid-cols-4"
        >
            <ProductCard
                v-for="product in props.featured"
                :key="product.id"
                :product="product"
            />
        </div>
        <p v-else class="text-muted-foreground">
            No products yet. Check back soon.
        </p>
    </section>
</template>
