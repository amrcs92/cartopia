<script setup lang="ts">
import { computed } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import StallButton from './StallButton.vue';

// The product card's add-to-basket control: a joined minus / quantity / plus /
// basket bar, or, when `compact`, the phone's full-width "Add" button. The
// quantity is v-model; `add` asks the caller to put it in the basket.
const props = withDefaults(
    defineProps<{
        productName: string;
        modelValue: number;
        min?: number;
        max?: number;
        compact?: boolean;
    }>(),
    { min: 1, max: 99, compact: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
    add: [];
}>();

const canDecrease = computed(() => props.modelValue > props.min);
const canIncrease = computed(() => props.modelValue < props.max);

function step(by: number): void {
    const next = Math.min(
        props.max,
        Math.max(props.min, props.modelValue + by),
    );

    if (next !== props.modelValue) {
        emit('update:modelValue', next);
    }
}

const stepper =
    'inline-flex w-11 shrink-0 focus-visible:outline-none items-center justify-center text-ink transition-colors duration-200 ease-stall hover:bg-field disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent';
</script>

<template>
    <StallButton
        v-if="props.compact"
        variant="marigold"
        class="w-full"
        :aria-label="`Add ${props.productName} to basket`"
        @click="emit('add')"
    >
        <Icon name="basket-add" :size="20" />
        Add
    </StallButton>
    <div
        v-else
        role="group"
        :aria-label="`Quantity and basket for ${props.productName}`"
        class="inline-flex h-12 items-stretch overflow-hidden rounded-xl border-[2.5px] border-outline bg-surface shadow-press has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marigold has-[:focus-visible]:outline-solid"
    >
        <button
            type="button"
            :class="stepper"
            :disabled="!canDecrease"
            :aria-label="`Decrease quantity of ${props.productName}`"
            @click="step(-1)"
        >
            <span aria-hidden="true" class="text-xl leading-none font-bold"
                >&minus;</span
            >
        </button>
        <output
            aria-live="polite"
            class="inline-flex min-w-8 items-center justify-center text-[17px] font-extrabold text-ink tabular-nums"
        >
            <span class="sr-only">Quantity </span>{{ props.modelValue }}
        </output>
        <button
            type="button"
            :class="stepper"
            :disabled="!canIncrease"
            :aria-label="`Increase quantity of ${props.productName}`"
            @click="step(1)"
        >
            <span aria-hidden="true" class="text-xl leading-none font-bold"
                >+</span
            >
        </button>
        <button
            type="button"
            class="inline-flex w-14 shrink-0 items-center justify-center border-l-[2.5px] border-outline bg-marigold text-on-marigold transition-colors duration-200 ease-stall hover:bg-marigold/90 focus-visible:outline-none"
            :aria-label="`Add ${props.productName} to basket`"
            @click="emit('add')"
        >
            <Icon name="basket-add" :size="22" />
        </button>
    </div>
</template>
