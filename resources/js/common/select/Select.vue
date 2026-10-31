<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/lib/shadcn/select';
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
        <SelectTrigger :id="props.id" :class="props.class">
            <SelectValue :placeholder="props.placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in props.options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
