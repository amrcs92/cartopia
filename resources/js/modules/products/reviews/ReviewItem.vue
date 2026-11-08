<script setup lang="ts">
import StarRating from '@/modules/products/StarRating.vue';
import type { Review } from '@/types';

// One review: who wrote it, their stars, the date, "Edited" when it changed
// after posting, and the text with its line breaks, wrapped anywhere so a long
// unbroken word never pushes the page sideways.
const props = defineProps<{
    review: Review;
}>();
</script>

<template>
    <article class="flex min-w-0 flex-col gap-2">
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[15px] text-subtle"
        >
            <span class="font-bold text-ink">{{ props.review.author }}</span>
            <StarRating :average="props.review.stars" />
            <span>{{ props.review.date }}</span>
            <span v-if="props.review.edited">Edited</span>
        </div>
        <p
            v-if="props.review.body"
            class="text-base leading-relaxed wrap-anywhere whitespace-pre-line text-body"
        >
            {{ props.review.body }}
        </p>
    </article>
</template>
