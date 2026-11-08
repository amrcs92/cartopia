<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue';

// The review form's stars: five 48px stars in a radio group. A star fills
// marigold up to the chosen value (or up to the star you're pointing at), and
// every star keeps its outline, so the value never rests on colour alone.
// Pressing a star chooses it; the form saves. The arrow keys move between
// stars (one tab stop for the group) and Enter or Space chooses, so focus can
// browse the stars without changing the value. Focus previews like hover.
const props = defineProps<{
    label: string;
}>();

const model = defineModel<number | null>({ required: true });

const stars = [1, 2, 3, 4, 5];

const hovered = ref<number | null>(null);
const focused = ref<number | null>(null);
const shown = computed(
    () => hovered.value ?? focused.value ?? model.value ?? 0,
);
const tabStop = computed(() => model.value ?? 1);

const buttons = useTemplateRef<HTMLButtonElement[]>('buttons');

function move(from: number, by: number): void {
    const to = Math.min(5, Math.max(1, from + by));

    buttons.value?.[to - 1]?.focus();
}

const STAR =
    'M12 2.8l2.85 5.78 6.38.93-4.62 4.5 1.09 6.35L12 17.36l-5.7 3 1.09-6.35-4.62-4.5 6.38-.93z';
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="props.label"
        class="flex gap-1"
        @mouseleave="hovered = null"
    >
        <button
            v-for="star in stars"
            ref="buttons"
            :key="star"
            type="button"
            role="radio"
            :aria-label="`${star} ${star === 1 ? 'star' : 'stars'}`"
            :aria-checked="model === star"
            :tabindex="star === tabStop ? 0 : -1"
            class="inline-flex size-12 items-center justify-center rounded-xl transition-colors duration-200 ease-stall hover:bg-field focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-marigold focus-visible:outline-solid"
            @click="model = star"
            @mouseenter="hovered = star"
            @focus="focused = star"
            @blur="focused = null"
            @keydown.right.prevent="move(star, 1)"
            @keydown.down.prevent="move(star, 1)"
            @keydown.left.prevent="move(star, -1)"
            @keydown.up.prevent="move(star, -1)"
            @keydown.home.prevent="move(star, -5)"
            @keydown.end.prevent="move(star, 5)"
        >
            <svg
                viewBox="0 0 24 24"
                width="40"
                height="40"
                aria-hidden="true"
                focusable="false"
                class="text-outline"
            >
                <path
                    :d="STAR"
                    :class="shown >= star ? 'fill-marigold' : 'fill-none'"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linejoin="round"
                />
            </svg>
        </button>
    </div>
</template>
