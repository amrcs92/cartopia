<script setup lang="ts">
import type { DropdownMenuContentProps } from 'reka-ui';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/lib/shadcn/dropdown-menu';

const props = defineProps<{
    align?: DropdownMenuContentProps['align'];
    contentClass?: string;
}>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <slot name="trigger" />
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="props.align" :class="props.contentClass">
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
