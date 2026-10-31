<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogFooter } from '@/lib/shadcn/dialog';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <DialogFooter :class="props.class">
        <slot />
    </DialogFooter>
</template>
