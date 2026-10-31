<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogDescription } from '@/lib/shadcn/dialog';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogDescription :class="props.class">
        <slot />
    </DialogDescription>
</template>
