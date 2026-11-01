<script setup lang="ts">
import { computed } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import type { IconName } from '@/common/icon/icons';
import { cn } from '@/lib/utils';
import { press, pressTransition, sticker, stickerFill } from './press';

// A 44 x 44 icon-only button; `label` is its accessible name. The default slot
// sits on top of the icon, for a count badge for example. Leave `icon` out to
// draw the button's content in the slot alone.
const props = withDefaults(
    defineProps<{
        label: string;
        icon?: IconName;
        variant?: 'surface' | 'green' | 'marigold';
    }>(),
    { variant: 'surface' },
);

const classes = computed(() =>
    cn(
        'relative inline-flex size-11 shrink-0 items-center justify-center rounded-xl',
        pressTransition,
        props.variant === 'surface'
            ? 'border-2 border-outline bg-surface text-ink hover:bg-field'
            : [stickerFill[props.variant], sticker.md, press.md],
    ),
);
</script>

<template>
    <button type="button" :aria-label="props.label" :class="classes">
        <Icon v-if="props.icon" :name="props.icon" :size="22" />
        <slot />
    </button>
</template>
