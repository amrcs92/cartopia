<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/lib/shadcn/select';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types';

const props = defineProps<{
    options: SelectOption[];
    id?: string;
    placeholder?: string;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string>();
</script>

<template>
    <Select v-model="model">
        <SelectTrigger
            :id="props.id"
            :class="
                cn(
                    'h-12 rounded-xl border-2 border-line-strong bg-field px-4 text-base font-semibold text-ink shadow-none data-[placeholder]:text-subtle data-[size=default]:h-12 dark:bg-field dark:hover:bg-field',
                    'transition-colors duration-200 ease-stall hover:border-outline [&_svg]:text-ink [&_svg]:opacity-100',
                    'focus-visible:border-outline focus-visible:ring-0 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-marigold focus-visible:outline-solid',
                    props.class,
                )
            "
        >
            <SelectValue :placeholder="props.placeholder" />
        </SelectTrigger>
        <SelectContent
            class="rounded-xl border-2 border-outline bg-surface text-ink shadow-press"
        >
            <SelectItem
                v-for="option in props.options"
                :key="option.value"
                :value="option.value"
                class="min-h-11 rounded-lg pl-3 text-base text-ink focus:bg-field focus:text-ink data-[state=checked]:font-bold"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
