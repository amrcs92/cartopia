<script setup lang="ts">
import Icon from '@/common/icon/Icon.vue';

// Up to two product photos side by side. With no photo at all (products can be
// without one) a basket placeholder keeps the row's shape.
const props = defineProps<{
    thumbnails: string[];
}>();
</script>

<template>
    <div class="flex shrink-0 -space-x-3" aria-hidden="true">
        <img
            v-for="(src, index) in props.thumbnails"
            :key="index"
            :src="src"
            alt=""
            loading="lazy"
            class="size-14 rounded-xl border-2 border-outline bg-well object-cover"
        />
        <span
            v-if="props.thumbnails.length === 0"
            class="inline-flex size-14 items-center justify-center rounded-xl border-2 border-outline bg-well text-ink"
        >
            <Icon name="basket" :size="26" />
        </span>
    </div>
</template>
