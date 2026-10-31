<script setup lang="ts">
import {
    clampQuantity,
    MAX_QUANTITY,
    MIN_QUANTITY,
} from '@/modules/cart/useCartActions';

// A joined minus / number / plus control. The number can be typed into; the
// value is always clamped to the allowed range.
const props = defineProps<{
    label: string;
    disabled?: boolean;
}>();

const model = defineModel<number>({ required: true });

function set(value: number): void {
    model.value = clampQuantity(value);
}

function onChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;

    if (value === '') {
        return;
    }

    set(Number(value));
}

const stepper =
    'inline-flex w-11 shrink-0 items-center justify-center text-xl leading-none font-bold text-ink transition-colors duration-200 ease-stall hover:bg-field disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent';
</script>

<template>
    <div
        class="inline-flex h-12 items-stretch overflow-hidden rounded-xl border-[2.5px] border-outline bg-surface"
    >
        <button
            type="button"
            :class="stepper"
            :aria-label="`Decrease quantity of ${props.label}`"
            :disabled="props.disabled || model <= MIN_QUANTITY"
            @click="set(model - 1)"
        >
            <span aria-hidden="true">&minus;</span>
        </button>
        <input
            :value="model"
            type="number"
            inputmode="numeric"
            :min="MIN_QUANTITY"
            :max="MAX_QUANTITY"
            :aria-label="`Quantity of ${props.label}`"
            :disabled="props.disabled"
            class="w-12 [appearance:textfield] bg-transparent text-center text-[17px] font-extrabold text-ink tabular-nums outline-none focus-visible:bg-field [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @change="onChange"
        />
        <button
            type="button"
            :class="stepper"
            :aria-label="`Increase quantity of ${props.label}`"
            :disabled="props.disabled || model >= MAX_QUANTITY"
            @click="set(model + 1)"
        >
            <span aria-hidden="true">+</span>
        </button>
    </div>
</template>
