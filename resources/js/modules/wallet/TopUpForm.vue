<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/common/app/InputError.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import StallButton from '@/common/stall/StallButton.vue';
import OptionCard from '@/common/stall/OptionCard.vue';
import { formatCents } from '@/modules/cart/money';
import type { HostedMethod } from '@/modules/payments/methods';
import { noteFor } from '@/modules/payments/methods';
import PayPalWordmark from '@/modules/payments/PayPalWordmark.vue';
import StripeWordmark from '@/modules/payments/StripeWordmark.vue';
import { usePaymentMethods } from '@/modules/payments/usePaymentMethods';
import { store } from '@/routes/wallet/top-ups';

// Quick amounts fill the field; the button says exactly what will be paid.
// The money is taken by Stripe or PayPal, so "Pay with" picks the method
// (Stripe by default) and the server sends the browser on to the provider.
const quickAmounts = [10, 25, 50, 100];

const methods = usePaymentMethods();

const hosted = {
    stripe: { component: StripeWordmark, name: 'Stripe' },
    paypal: { component: PayPalWordmark, name: 'PayPal' },
} as const;

const form = useForm<{ amount: string; payment_method: HostedMethod | '' }>({
    amount: '',
    payment_method: methods.value.includes('stripe')
        ? 'stripe'
        : (methods.value[0] ?? ''),
});

const amountCents = computed(() => {
    const value = Number(form.amount);

    return form.amount !== '' && Number.isFinite(value) && value > 0
        ? Math.round(value * 100)
        : null;
});

const buttonLabel = computed(() =>
    amountCents.value === null
        ? 'Continue to pay'
        : `Continue to pay ${formatCents(amountCents.value)}`,
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

        <fieldset v-if="methods.length > 0" class="space-y-3">
            <legend class="mb-3 font-bold text-ink">Pay with</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <OptionCard
                    v-for="method in methods"
                    :key="method"
                    :selected="form.payment_method === method"
                >
                    <input
                        v-model="form.payment_method"
                        type="radio"
                        name="payment_method"
                        class="sr-only"
                        :value="method"
                        :data-test="`topup-pay-${method}`"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="sr-only">{{ hosted[method].name }}</span>
                        <component :is="hosted[method].component" />
                        <span class="block text-[15px] text-body">
                            {{ noteFor(method) }}
                        </span>
                    </span>
                </OptionCard>
            </div>
            <InputError :message="form.errors.payment_method" />
        </fieldset>
        <p v-else class="text-[15px] text-body" data-test="top-up-not-set-up">
            Stripe and PayPal payments aren't set up on this copy of Cartopia.
        </p>

        <StallButton
            variant="marigold"
            size="lg"
            type="submit"
            :disabled="form.processing || methods.length === 0"
            data-test="top-up"
            class="w-full sm:w-auto"
        >
            {{ buttonLabel }}
        </StallButton>
    </form>
</template>
