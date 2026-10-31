<script setup lang="ts">
import Icon from '@/common/icon/Icon.vue';

// A 38px pill that toggles a shop filter (a price shortcut, a rating step or
// an aisle in the phone row). Pressed chips turn green with a check mark, so
// the state is never colour alone; an invisible hit area makes the target
// 44px tall. `tone` tints an unpressed chip (the phone aisle row).
const props = withDefaults(
    defineProps<{
        pressed: boolean;
        tone?: 'plain' | 'tint-1' | 'tint-2' | 'tint-3';
    }>(),
    { tone: 'plain' },
);

const tones = {
    plain: 'border-line-strong bg-surface',
    'tint-1': 'border-outline bg-tint-1',
    'tint-2': 'border-outline bg-tint-2',
    'tint-3': 'border-outline bg-tint-3',
} as const;
</script>

<template>
    <button
        type="button"
        :aria-pressed="props.pressed"
        class="relative inline-flex h-[38px] shrink-0 items-center gap-1.5 rounded-full border-[1.5px] px-4 text-[15px] font-bold whitespace-nowrap transition-colors duration-200 ease-stall after:absolute after:inset-x-0 after:-inset-y-[3px] after:content-['']"
        :class="
            props.pressed
                ? 'border-outline bg-green text-white'
                : [tones[props.tone], 'text-ink hover:border-outline']
        "
    >
        <Icon v-if="props.pressed" name="check" :size="16" />
        <slot />
    </button>
</template>
