<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { ButtonVariants } from '@/lib/shadcn/button';
import { Button } from '@/lib/shadcn/button';
import { press, pressTransition, sticker } from '@/common/stall/press';
import { cn } from '@/lib/utils';

const props = defineProps<{
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    as?: string;
    asChild?: boolean;
    class?: HTMLAttributes['class'];
}>();

type Variant = NonNullable<ButtonVariants['variant']>;
type Size = NonNullable<ButtonVariants['size']>;

// The stall look laid over the shadcn variants: the solid ones are stickers
// with a hard shadow and the press motion; every size is at least 44px tall.
const variants: Record<Variant, string> = {
    default: cn(
        sticker.md,
        press.md,
        'bg-green text-primary-foreground hover:bg-green/90',
    ),
    destructive: cn(
        sticker.md,
        press.md,
        'bg-danger text-destructive-foreground hover:bg-danger/90 dark:bg-danger',
    ),
    outline:
        'border-2 border-outline bg-surface text-ink shadow-none hover:bg-field hover:text-ink dark:border-outline dark:bg-surface dark:hover:bg-field',
    secondary: 'bg-field text-ink hover:bg-line',
    ghost: 'text-ink hover:bg-field hover:text-ink dark:hover:bg-field',
    link: 'text-link',
};

const sizes: Record<Size, string> = {
    default: 'h-12 px-6 has-[>svg]:px-5',
    sm: 'h-11 px-4 text-[15px] has-[>svg]:px-3.5',
    lg: 'h-14 rounded-[14px] px-7 text-lg has-[>svg]:px-6',
    icon: 'size-11',
    'icon-sm': 'size-11',
    'icon-lg': 'size-12',
};

const base = cn(
    'rounded-xl font-sans text-base font-extrabold',
    pressTransition,
    'focus-visible:border-outline focus-visible:ring-0 focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-marigold focus-visible:outline-solid',
);
</script>

<template>
    <Button
        :variant="props.variant"
        :size="props.size"
        :as="props.as"
        :as-child="props.asChild"
        :class="
            cn(
                base,
                variants[props.variant ?? 'default'],
                sizes[props.size ?? 'default'],
                props.class,
            )
        "
    >
        <slot />
    </Button>
</template>
