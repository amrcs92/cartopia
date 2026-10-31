<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    average: number;
    count?: number;
}>();

const stars = [1, 2, 3, 4, 5];

// Display only: a star is filled once the average reaches it (rounded to 0.5).
const rounded = computed(() => Math.round(props.average * 2) / 2);
</script>

<template>
    <div
        class="flex items-center gap-1 text-sm"
        :aria-label="`Rated ${props.average} out of 5`"
    >
        <div class="flex" aria-hidden="true">
            <Star
                v-for="star in stars"
                :key="star"
                class="size-4"
                :class="
                    rounded >= star
                        ? 'fill-amber-400 text-amber-400'
                        : rounded >= star - 0.5
                          ? 'fill-amber-400/50 text-amber-400'
                          : 'text-muted-foreground/40'
                "
            />
        </div>
        <span v-if="props.count !== undefined" class="text-muted-foreground">
            <template v-if="props.count > 0">
                {{ props.average.toFixed(1) }} ({{ props.count }})
            </template>
            <template v-else>No ratings yet</template>
        </span>
    </div>
</template>
