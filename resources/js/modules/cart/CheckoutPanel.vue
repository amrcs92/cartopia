<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/common/app/InputError.vue';
import Icon from '@/common/icon/Icon.vue';
import OptionCard from '@/common/stall/OptionCard.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';
import { formatCents, splitPlan } from '@/modules/cart/money';
import PayWithOptions from '@/modules/cart/PayWithOptions.vue';
import { feeLabel, isPickup } from '@/modules/home/shipping';
import { buttonLabel, MIN_HOSTED_CENTS } from '@/modules/payments/methods';
import type { HostedMethod } from '@/modules/payments/methods';
import { usePaymentMethods } from '@/modules/payments/usePaymentMethods';
import { start, store } from '@/routes/checkout';
import type { Cart, ShippingMethod } from '@/types';

// The checkout half of the order summary: "How do you want it?" (one option
// card per shipping method), "Pay with", the total and "Place order and pay".
// Guests see "Log in to check out" instead. Below 640px the total and a
// "Place order" button are pinned to the bottom of the screen. The total is
// the subtotal plus the chosen fee, from the *_cents fields.
const props = defineProps<{
    cart: Cart;
    shippingMethods: ShippingMethod[];
}>();

const page = usePage();
const user = useCurrentUser();

// The refusal (low balance, empty basket) arrives as Inertia's one-shot
// `checkoutError` flash: read when the page opens and on the router's `flash`
// event, and never replayed by Back/Forward.
const flashError = ref<string | null>(
    (page.flash as { checkoutError?: string } | undefined)?.checkoutError ??
        null,
);

let stopListening: (() => void) | undefined;

onMounted(() => {
    stopListening = router.on('flash', (event) => {
        const value = (event as CustomEvent).detail?.flash?.checkoutError as
            | string
            | undefined;

        if (value) {
            flashError.value = value;
        }
    });
});

onBeforeUnmount(() => stopListening?.());

const methods = usePaymentMethods();

// The wallet when it covers the total; otherwise the first hosted method.
function defaultChoice(total: number): 'wallet' | HostedMethod | null {
    return (user.value?.balance_cents ?? 0) >= total
        ? 'wallet'
        : (methods.value[0] ?? null);
}

function choiceIsValid(
    choice: 'wallet' | HostedMethod | null,
    total: number,
): boolean {
    if (choice === 'wallet') {
        return (user.value?.balance_cents ?? 0) >= total;
    }

    return (
        choice !== null &&
        methods.value.includes(choice) &&
        total >= MIN_HOSTED_CENTS
    );
}

const form = useForm<{
    shipping_method_id: number;
    payment_method: 'wallet' | HostedMethod | null;
    use_wallet: boolean;
}>({
    shipping_method_id: props.shippingMethods[0]?.id ?? 0,
    payment_method: null,
    use_wallet: true,
});

const method = computed(() =>
    props.shippingMethods.find((m) => m.id === form.shipping_method_id),
);

const totalCents = computed(
    () => props.cart.subtotal_cents + (method.value?.fee_cents ?? 0),
);
const total = computed(() => formatCents(totalCents.value));

form.payment_method = defaultChoice(totalCents.value);

// A new shipping fee changes the total: pick a fresh default only when the
// current choice stopped being possible, so the shopper's own pick stands.
watch(totalCents, (value) => {
    if (!choiceIsValid(form.payment_method, value)) {
        form.payment_method = defaultChoice(value);
    }
});

// The switch card shows when a hosted method is available and the wallet can
// pay part of the order; only then is `use_wallet` sent.
const balanceCents = computed(() => user.value?.balance_cents ?? 0);
const switchShown = computed(
    () =>
        methods.value.length > 0 &&
        splitPlan(totalCents.value, balanceCents.value, true).walletCents > 0,
);
const plan = computed(() =>
    splitPlan(
        totalCents.value,
        balanceCents.value,
        switchShown.value && form.use_wallet,
    ),
);
const splitLine = computed(
    () =>
        `Wallet ${formatCents(plan.value.walletCents)} · ${
            form.payment_method === 'paypal' ? 'PayPal' : 'Stripe'
        } ${formatCents(plan.value.hostedCents)}`,
);

const canOrder = computed(
    () =>
        !form.processing &&
        method.value !== undefined &&
        choiceIsValid(form.payment_method, totalCents.value) &&
        (form.payment_method === 'wallet' ||
            plan.value.hostedCents >= MIN_HOSTED_CENTS),
);

const buttonText = computed(() =>
    buttonLabel(
        form.payment_method ?? 'wallet',
        form.payment_method === 'wallet'
            ? totalCents.value
            : plan.value.hostedCents,
    ),
);

function submit(): void {
    if (canOrder.value) {
        form.transform((data) => {
            const { use_wallet: useWallet, ...rest } = data;

            return switchShown.value
                ? { ...rest, use_wallet: useWallet }
                : rest;
        }).submit(store());
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <template v-if="user">
            <fieldset class="flex flex-col gap-3">
                <legend class="mb-3 font-serif text-[20px] text-ink">
                    How do you want it?
                </legend>
                <OptionCard
                    v-for="option in props.shippingMethods"
                    :key="option.id"
                    :selected="option.id === form.shipping_method_id"
                    data-test="shipping-option"
                >
                    <input
                        v-model="form.shipping_method_id"
                        type="radio"
                        name="shipping_method_id"
                        class="sr-only"
                        :value="option.id"
                    />
                    <span
                        aria-hidden="true"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-ink"
                        :class="isPickup(option) ? 'bg-chip' : 'bg-tint-3'"
                    >
                        <Icon
                            :name="isPickup(option) ? 'pickup' : 'delivery'"
                            :size="22"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-extrabold text-ink">
                            {{ option.name }}
                        </span>
                        <span class="block text-[15px] text-body">
                            {{
                                isPickup(option)
                                    ? 'Collect your order yourself.'
                                    : 'Delivered to your address.'
                            }}
                        </span>
                    </span>
                    <span class="shrink-0 font-serif text-[20px] text-ink">
                        {{ feeLabel(option) }}
                    </span>
                </OptionCard>
                <InputError :message="form.errors.shipping_method_id" />
            </fieldset>

            <PayWithOptions
                v-model="form.payment_method"
                v-model:use-wallet="form.use_wallet"
                :balance="user.balance"
                :balance-cents="user.balance_cents"
                :total-cents="totalCents"
                :methods="methods"
            />

            <p
                v-if="flashError"
                class="text-sm text-danger"
                role="alert"
                data-test="checkout-error"
            >
                {{ flashError }}
            </p>

            <div
                class="flex items-baseline justify-between border-t-[1.5px] border-line pt-4"
            >
                <span class="font-bold text-ink">Total</span>
                <span
                    class="font-serif text-[30px] text-ink tabular-nums"
                    data-test="cart-total"
                >
                    {{ total }}
                </span>
            </div>

            <p
                v-if="switchShown && form.use_wallet"
                class="-mt-3 text-right text-[15px] font-bold text-body tabular-nums"
                data-test="split-line"
            >
                {{ splitLine }}
            </p>

            <StallButton
                variant="green"
                size="lg"
                class="w-full max-sm:hidden"
                :disabled="!canOrder"
                data-test="checkout-button"
                @click="submit"
            >
                {{ buttonText }}
            </StallButton>

            <div
                class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-4 border-t-[2.5px] border-outline bg-surface px-4 py-3 sm:hidden"
                data-test="checkout-bar"
            >
                <div>
                    <p class="text-[13px] text-body">Total</p>
                    <p class="font-serif text-[24px] leading-none text-ink">
                        {{ total }}
                    </p>
                </div>
                <StallButton
                    variant="green"
                    :disabled="!canOrder"
                    data-test="checkout-bar-button"
                    @click="submit"
                >
                    Place order
                </StallButton>
            </div>
        </template>

        <template v-else>
            <p
                v-if="flashError"
                class="text-sm text-danger"
                role="alert"
                data-test="checkout-error"
            >
                {{ flashError }}
            </p>
            <StallButton
                as="link"
                variant="green"
                size="lg"
                class="w-full"
                :href="start().url"
                data-test="login-to-checkout"
            >
                Log in to check out
            </StallButton>
        </template>
    </div>
</template>
