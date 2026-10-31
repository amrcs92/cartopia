<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogContent } from '@/lib/shadcn/dialog';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogContent :class="props.class">
        <slot />
    </DialogContent>
</template>
