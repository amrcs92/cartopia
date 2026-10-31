<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import StarRating from '@/modules/products/StarRating.vue';
import { index as productsIndex } from '@/routes/products';
import type { ProductDetail } from '@/types';

const props = defineProps<{
    product: ProductDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: productsIndex() }],
    },
});

const selected = ref(0);
const currentImage = computed(
    () => props.product.images[selected.value] ?? props.product.image_url,
);
</script>

<template>
    <Head :title="props.product.name" />

    <div class="grid gap-8 md:grid-cols-2">
        <div class="space-y-3">
            <div
                class="aspect-square overflow-hidden rounded-xl border bg-muted"
            >
                <img
                    v-if="currentImage"
                    :src="currentImage"
                    :alt="props.product.name"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center text-muted-foreground"
                >
                    <ImageOff class="size-10" aria-hidden="true" />
                </div>
            </div>

            <div
                v-if="props.product.images.length > 1"
                class="flex flex-wrap gap-2"
            >
                <button
                    v-for="(image, index) in props.product.images"
                    :key="image"
                    type="button"
                    class="size-16 overflow-hidden rounded-md border-2 transition"
                    :class="
                        index === selected
                            ? 'border-primary'
                            : 'border-transparent opacity-70 hover:opacity-100'
                    "
                    :aria-label="`Show image ${index + 1}`"
                    :aria-pressed="index === selected"
                    @click="selected = index"
                >
                    <img :src="image" alt="" class="size-full object-cover" />
                </button>
            </div>
        </div>

        <div class="space-y-5">
            <div class="space-y-2">
                <h1 class="text-3xl font-semibold tracking-tight">
                    {{ props.product.name }}
                </h1>
                <StarRating
                    :average="props.product.rating_average"
                    :count="props.product.rating_count"
                />
            </div>

            <p class="text-3xl font-semibold" data-test="price">
                {{ props.product.price }}
            </p>

            <p
                v-if="props.product.my_rating !== null"
                class="text-sm text-muted-foreground"
            >
                You rated this {{ props.product.my_rating }} out of 5.
            </p>

            <div class="space-y-2">
                <h2 class="text-lg font-medium">Description</h2>
                <p
                    class="leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ props.product.description }}
                </p>
            </div>
        </div>
    </div>
</template>
