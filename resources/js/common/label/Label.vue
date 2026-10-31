<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Label } from '@/lib/shadcn/label';

const props = defineProps<{
    for?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Label :for="props.for" :class="props.class">
        <slot />
    </Label>
</template>
