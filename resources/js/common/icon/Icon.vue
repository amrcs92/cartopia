<script setup lang="ts">
import { computed } from 'vue';
import { icons } from './icons';
import type { IconName } from './icons';

const props = withDefaults(
    defineProps<{
        name: IconName;
        size?: number;
    }>(),
    { size: 24 },
);

const shape = computed(() => icons[props.name]);
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        :width="props.size"
        :height="props.size"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <path v-for="d in shape.paths" :key="d" :d="d" />
        <circle
            v-for="[cx, cy, r] in shape.circles ?? []"
            :key="`${cx}-${cy}`"
            :cx="cx"
            :cy="cy"
            :r="r"
        />
    </svg>
</template>
