<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { press, pressTransition, sticker, stickerFill } from './press';

// A button, or an Inertia link styled as one. A rendered <button> defaults to
// type="button"; pass type="submit" for forms (attributes fall through).
const props = withDefaults(
    defineProps<{
        variant: 'marigold' | 'green' | 'outline' | 'quiet';
        size?: 'md' | 'lg';
        as?: 'button' | 'link';
        href?: string;
        disabled?: boolean;
    }>(),
    { size: 'md', as: 'button', href: undefined, disabled: false },
);

const classes = computed(() => {
    const { variant, size, disabled } = props;

    return cn(
        'inline-flex items-center justify-center gap-2 rounded-xl font-sans font-extrabold whitespace-nowrap select-none',
        pressTransition,
        size === 'lg'
            ? 'min-h-14 rounded-[14px] px-7 text-[17px] lg:text-lg'
            : 'min-h-11 px-5 text-[15px] lg:text-base',
        (variant === 'marigold' || variant === 'green') && [
            stickerFill[variant],
            sticker[size],
            !disabled && press[size],
        ],
        variant === 'outline' &&
            'border-2 border-outline bg-surface text-ink hover:bg-field',
        variant === 'quiet' &&
            'text-link underline-offset-4 hover:bg-field hover:underline',
        disabled && 'cursor-not-allowed opacity-50',
    );
});
</script>

<template>
    <template v-if="props.as === 'link'">
        <span
            v-if="props.disabled"
            role="link"
            aria-disabled="true"
            :class="classes"
        >
            <slot />
        </span>
        <Link v-else :href="props.href ?? ''" :class="classes">
            <slot />
        </Link>
    </template>
    <button v-else type="button" :disabled="props.disabled" :class="classes">
        <slot />
    </button>
</template>
