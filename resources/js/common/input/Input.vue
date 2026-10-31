<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Input } from '@/lib/shadcn/input';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string | number>();
</script>

<template>
    <Input v-model="model" :class="props.class" />
</template>
