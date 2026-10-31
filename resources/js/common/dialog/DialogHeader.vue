<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogHeader } from '@/lib/shadcn/dialog';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogHeader :class="props.class">
        <slot />
    </DialogHeader>
</template>
