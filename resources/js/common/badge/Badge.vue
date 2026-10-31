<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { BadgeVariants } from '@/lib/shadcn/badge';
import { Badge } from '@/lib/shadcn/badge';

const props = defineProps<{
    variant?: BadgeVariants['variant'];
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Badge :variant="props.variant" :class="props.class">
        <slot />
    </Badge>
</template>
