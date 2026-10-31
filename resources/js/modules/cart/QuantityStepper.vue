<script setup lang="ts">
import { computed } from 'vue';
import {
    clampQuantity,
    MAX_QUANTITY,
    MIN_QUANTITY,
} from '@/modules/cart/useCartActions';

// A joined minus / number / plus control. The number can be typed into; the
// value is always clamped to the allowed range. `size="lg"` is the taller
// control beside "Add to basket" on the product page. The marigold focus ring
// is drawn on the wrapper (the buttons would be clipped by its rounded edge).
const props = withDefaults(
    defineProps<{
        label: string;
        disabled?: boolean;
        size?: 'md' | 'lg';
    }>(),
    { size: 'md' },
);

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

    // Vue skips the patch when a clamped value equals the old one, so write
    // the clamped number back into the field ourselves.
    (event.target as HTMLInputElement).value = String(model.value);
}

const large = computed(() => props.size === 'lg');

const stepper =
    'inline-flex shrink-0 focus-visible:outline-none items-center justify-center leading-none font-bold text-ink transition-colors duration-200 ease-stall hover:bg-field disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent';
</script>

<template>
    <div
        class="inline-flex items-stretch overflow-hidden border-[2.5px] border-outline bg-surface has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marigold has-[:focus-visible]:outline-solid"
        :class="large ? 'h-[58px] rounded-2xl' : 'h-12 rounded-xl'"
    >
        <button
            type="button"
            :class="[stepper, large ? 'w-12 text-2xl' : 'w-11 text-xl']"
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
            :class="large ? 'text-lg' : 'text-[17px]'"
            class="w-12 [appearance:textfield] bg-transparent text-center font-extrabold text-ink tabular-nums focus-visible:bg-field focus-visible:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @change="onChange"
        />
        <button
            type="button"
            :class="[stepper, large ? 'w-12 text-2xl' : 'w-11 text-xl']"
            :aria-label="`Increase quantity of ${props.label}`"
            :disabled="props.disabled || model >= MAX_QUANTITY"
            @click="set(model + 1)"
        >
            <span aria-hidden="true">+</span>
        </button>
    </div>
</template>
