<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import Button from '@/common/button/Button.vue';
import Input from '@/common/input/Input.vue';
import {
    clampQuantity,
    MAX_QUANTITY,
    MIN_QUANTITY,
} from '@/modules/cart/useCartActions';

const props = defineProps<{
    label: string;
    disabled?: boolean;
}>();

const model = defineModel<number>({ required: true });

function set(value: number): void {
    model.value = clampQuantity(value);
}

function onInput(value: string | number | undefined): void {
    if (value === '' || value === undefined) {
        return;
    }

    set(Number(value));
}
</script>

<template>
    <div class="inline-flex items-center gap-1">
        <Button
            variant="outline"
            size="icon"
            type="button"
            :aria-label="`Decrease quantity of ${props.label}`"
            :disabled="props.disabled || model <= MIN_QUANTITY"
            @click="set(model - 1)"
        >
            <Minus class="size-4" aria-hidden="true" />
        </Button>
        <Input
            :model-value="model"
            type="number"
            inputmode="numeric"
            :min="MIN_QUANTITY"
            :max="MAX_QUANTITY"
            :aria-label="`Quantity of ${props.label}`"
            :disabled="props.disabled"
            class="w-16 text-center tabular-nums"
            @update:model-value="onInput"
        />
        <Button
            variant="outline"
            size="icon"
            type="button"
            :aria-label="`Increase quantity of ${props.label}`"
            :disabled="props.disabled || model >= MAX_QUANTITY"
            @click="set(model + 1)"
        >
            <Plus class="size-4" aria-hidden="true" />
        </Button>
    </div>
</template>
