<script setup lang="ts">
import { computed } from 'vue';

// The stall mark (design-assets.md, "Logo mark") beside the Young Serif
// wordmark. The mark's colours are theme tokens, so in dark mode its outlines,
// wheels and awning turn cream. The wordmark inherits the surrounding text
// colour (ink on the page), so it also reads on dark bands such as the footer.
const props = withDefaults(
    defineProps<{
        variant?: 'full' | 'mark';
        size?: 'header' | 'phone';
    }>(),
    { variant: 'full', size: 'header' },
);

const phone = computed(() => props.size === 'phone');
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center"
        :class="phone ? 'gap-2' : 'gap-3'"
    >
        <svg
            viewBox="0 0 44 44"
            aria-hidden="true"
            focusable="false"
            class="shrink-0"
            :class="phone ? 'size-[34px]' : 'size-11'"
        >
            <path
                d="M4 14h36l-3 4H7z"
                class="fill-green stroke-outline"
                stroke-width="2.5"
                stroke-linejoin="round"
            />
            <path d="M8 4h28l4 10H4z" class="fill-stripe" />
            <path
                d="M15 4l-3 10M22 4v10M29 4l3 10"
                class="stroke-green"
                stroke-width="5"
            />
            <path
                d="M8 4h28l4 10H4z"
                fill="none"
                class="stroke-outline"
                stroke-width="2.5"
                stroke-linejoin="round"
            />
            <rect
                x="9"
                y="22"
                width="26"
                height="14"
                rx="3"
                class="fill-marigold stroke-outline"
                stroke-width="2.5"
            />
            <circle cx="14" cy="39.5" r="2.5" class="fill-outline" />
            <circle cx="30" cy="39.5" r="2.5" class="fill-outline" />
        </svg>
        <span
            v-if="props.variant === 'full'"
            class="font-serif leading-none font-normal"
            :class="phone ? 'text-[23px]' : 'text-[28px]'"
        >
            Cartopia
        </span>
        <span v-else class="sr-only">Cartopia</span>
    </span>
</template>
