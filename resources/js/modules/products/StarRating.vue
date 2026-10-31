<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

// Display only: a star is filled once the average reaches it (rounded to 0.5).
// With a `count`, the line reads "4.0 from 1 rating", or "No ratings yet".
const props = defineProps<{
    average: number;
    count?: number;
}>();

const stars = [1, 2, 3, 4, 5];

const rounded = computed(() => Math.round(props.average * 2) / 2);
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[13px] font-semibold text-subtle sm:text-[15px]"
    >
        <template v-if="props.count === 0">No ratings yet</template>
        <template v-else>
            <span class="flex text-star" aria-hidden="true">
                <Star
                    v-for="star in stars"
                    :key="star"
                    class="size-4"
                    :class="
                        rounded >= star
                            ? 'fill-star'
                            : rounded >= star - 0.5
                              ? 'fill-star/50'
                              : 'text-line-strong'
                    "
                />
            </span>
            <span v-if="props.count !== undefined">
                {{ props.average.toFixed(1) }} from {{ props.count }}
                {{ props.count === 1 ? 'rating' : 'ratings' }}
            </span>
            <span v-else class="sr-only">
                Rated {{ props.average.toFixed(1) }} out of 5
            </span>
        </template>
    </div>
</template>
