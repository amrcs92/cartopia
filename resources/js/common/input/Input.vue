<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Input } from '@/lib/shadcn/input';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string | number>();
</script>

<template>
    <Input
        v-model="model"
        :class="
            cn(
                'h-12 rounded-xl border-2 border-line-strong bg-field px-4 text-base text-ink shadow-none placeholder:text-subtle md:text-base dark:bg-field',
                'transition-colors duration-200 ease-stall hover:border-outline',
                'focus-visible:border-outline focus-visible:ring-0 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-marigold focus-visible:outline-solid',
                'aria-invalid:border-danger aria-invalid:ring-0',
                props.class,
            )
        "
    />
</template>
