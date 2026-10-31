<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Card } from '@/lib/shadcn/card';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Card :class="props.class">
        <slot />
    </Card>
</template>
