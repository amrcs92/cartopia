<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DropdownMenuItem } from '@/lib/shadcn/dropdown-menu';

const props = defineProps<{
    asChild?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DropdownMenuItem :as-child="props.asChild" :class="props.class">
        <slot />
    </DropdownMenuItem>
</template>
