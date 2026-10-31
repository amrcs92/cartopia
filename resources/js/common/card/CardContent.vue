<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { CardContent } from '@/lib/shadcn/card';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <CardContent :class="props.class">
        <slot />
    </CardContent>
</template>
