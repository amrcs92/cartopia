<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { ButtonVariants } from '@/lib/shadcn/button';
import { Button } from '@/lib/shadcn/button';

const props = defineProps<{
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    as?: string;
    asChild?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Button
        :variant="props.variant"
        :size="props.size"
        :as="props.as"
        :as-child="props.asChild"
        :class="props.class"
    >
        <slot />
    </Button>
</template>
