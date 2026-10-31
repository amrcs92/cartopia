<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import Card from '@/common/card/Card.vue';
import CardContent from '@/common/card/CardContent.vue';
import StarRating from '@/modules/products/StarRating.vue';
import { show } from '@/routes/products';
import type { Product } from '@/types';

const props = defineProps<{
    product: Product;
}>();
</script>

<template>
    <Card class="group gap-0 overflow-hidden py-0 transition hover:shadow-md">
        <Link :href="show(props.product)" class="block">
            <div class="aspect-square overflow-hidden bg-muted">
                <img
                    v-if="props.product.image_url"
                    :src="props.product.image_url"
                    :alt="props.product.name"
                    loading="lazy"
                    class="size-full object-cover transition duration-300 group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center text-muted-foreground"
                >
                    <ImageOff class="size-8" aria-hidden="true" />
                </div>
            </div>
        </Link>
        <CardContent class="flex flex-1 flex-col gap-2 p-4">
            <Link
                :href="show(props.product)"
                class="line-clamp-2 font-medium hover:underline"
            >
                {{ props.product.name }}
            </Link>
            <StarRating
                :average="props.product.rating_average"
                :count="props.product.rating_count"
            />
            <p class="mt-auto text-lg font-semibold">
                {{ props.product.price }}
            </p>
        </CardContent>
    </Card>
</template>
