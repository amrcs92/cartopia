<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DropdownMenuLabel } from '@/lib/shadcn/dropdown-menu';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DropdownMenuLabel :class="props.class">
        <slot />
    </DropdownMenuLabel>
</template>
