<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { CardFooter } from '@/lib/shadcn/card';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <CardFooter :class="props.class">
        <slot />
    </CardFooter>
</template>
