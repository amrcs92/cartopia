<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/common/app/InputError.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { formatCents } from '@/modules/cart/money';
import { store } from '@/routes/wallet/top-ups';

// Quick amounts fill the field; the button says exactly what will be added.
// Top-ups are free credit for now, so there is no "Pay with" choice.
const quickAmounts = [10, 25, 50, 100];

const form = useForm({
    amount: '',
});

const amountCents = computed(() => {
    const value = Number(form.amount);

    return form.amount !== '' && Number.isFinite(value) && value > 0
        ? Math.round(value * 100)
        : null;
});

const buttonLabel = computed(() =>
    amountCents.value === null
        ? 'Add to my wallet'
        : `Add ${formatCents(amountCents.value)} to my wallet`,
);

function submit(): void {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div
            role="group"
            aria-label="Quick amounts"
            class="flex flex-wrap gap-3"
        >
            <button
                v-for="amount in quickAmounts"
                :key="amount"
                type="button"
                class="inline-flex min-h-11 min-w-16 items-center justify-center rounded-full border-2 px-4 text-[15px] font-extrabold text-ink transition-colors duration-200 ease-stall"
                :class="
                    Number(form.amount) === amount && form.amount !== ''
                        ? 'border-outline bg-selected'
                        : 'border-line-strong bg-surface hover:border-outline'
                "
                :aria-pressed="Number(form.amount) === amount"
                :data-test="`quick-amount-${amount}`"
                @click="form.amount = String(amount)"
            >
                ${{ amount }}
            </button>
        </div>

        <div class="space-y-2">
            <Label for="top-up-amount" class="font-bold text-ink">
                Amount (USD)
            </Label>
            <Input
                id="top-up-amount"
                v-model="form.amount"
                type="number"
                inputmode="decimal"
                min="5"
                max="500"
                step="0.01"
                placeholder="25.00"
                required
            />
            <p class="text-sm text-body">Between $5 and $500 per top-up.</p>
            <InputError :message="form.errors.amount" />
        </div>

        <StallButton
            variant="marigold"
            size="lg"
            type="submit"
            :disabled="form.processing"
            data-test="top-up"
            class="w-full sm:w-auto"
        >
            {{ buttonLabel }}
        </StallButton>
    </form>
</template>
