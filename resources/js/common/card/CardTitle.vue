<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { CardTitle } from '@/lib/shadcn/card';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <CardTitle :class="props.class">
        <slot />
    </CardTitle>
</template>
