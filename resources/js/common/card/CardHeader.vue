<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { CardHeader } from '@/lib/shadcn/card';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <CardHeader :class="props.class">
        <slot />
    </CardHeader>
</template>
