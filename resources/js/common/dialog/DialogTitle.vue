<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogTitle } from '@/lib/shadcn/dialog';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogTitle :class="props.class">
        <slot />
    </DialogTitle>
</template>
