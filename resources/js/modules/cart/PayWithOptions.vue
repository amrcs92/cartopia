<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import OptionCard from '@/common/stall/OptionCard.vue';
import { formatCents, splitPlan } from '@/modules/cart/money';
import { MIN_HOSTED_CENTS, noteFor } from '@/modules/payments/methods';
import type { HostedMethod } from '@/modules/payments/methods';
import PayPalWordmark from '@/modules/payments/PayPalWordmark.vue';
import StripeWordmark from '@/modules/payments/StripeWordmark.vue';
import { show as showWallet } from '@/routes/wallet';

// "Pay with": the wallet and the hosted methods (card, PayPal) as option
// cards sharing one radio group. When the balance cannot cover the total the
// wallet card is disabled and says how much more is needed, with a top-up
// link. When a hosted method is available and the wallet holds something, it
// becomes a "My $X balance" switch card that pays part of the order. Below the
// hosted minimum the Stripe and PayPal cards are disabled.
const props = defineProps<{
    balance: string;
    balanceCents: number;
    totalCents: number;
    methods: HostedMethod[];
}>();

const model = defineModel<'wallet' | HostedMethod | null>({ required: true });

const useWallet = defineModel<boolean>('useWallet', { default: true });

const shortfall = computed(() => props.totalCents - props.balanceCents);
const tooLow = computed(() => shortfall.value > 0);
// The switch card shows when the wallet can pay a real part of the order.
const switchShown = computed(
    () =>
        props.methods.length > 0 &&
        splitPlan(props.totalCents, props.balanceCents, true).walletCents > 0,
);
const belowMinimum = computed(() => props.totalCents < MIN_HOSTED_CENTS);

const hosted = {
    stripe: { component: StripeWordmark, name: 'Card' },
    paypal: { component: PayPalWordmark, name: 'PayPal' },
} as const;
</script>

<template>
    <fieldset class="flex flex-col gap-3">
        <legend class="mb-3 font-serif text-[20px] text-ink">Pay with</legend>
        <OptionCard v-if="switchShown" :selected="useWallet">
            <span class="min-w-0 flex-1">
                <span class="flex min-h-9 items-center gap-2.5">
                    <span
                        aria-hidden="true"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg border-2 border-outline bg-marigold text-on-marigold"
                    >
                        <Icon name="wallet" :size="16" />
                    </span>
                    <span
                        class="font-serif text-[20px] whitespace-nowrap text-ink"
                    >
                        My {{ props.balance }} balance
                    </span>
                </span>
                <span
                    id="split-note"
                    class="mt-2 block text-[14px] leading-[1.45] font-semibold text-body"
                >
                    Switch on, then pay the rest with Stripe or PayPal below.
                </span>
            </span>
            <span
                class="relative inline-flex shrink-0 items-center self-center"
            >
                <input
                    v-model="useWallet"
                    type="checkbox"
                    role="switch"
                    class="absolute inset-0 m-0 size-full cursor-pointer opacity-0"
                    :aria-label="`Use my ${props.balance} balance`"
                    aria-describedby="split-note"
                    data-test="wallet-switch"
                />
                <span
                    aria-hidden="true"
                    class="flex h-9 w-[62px] items-center rounded-full border-[2.5px] border-outline px-1 shadow-press transition-colors duration-200 ease-stall"
                    :class="useWallet ? 'bg-green' : 'bg-field'"
                >
                    <span
                        class="flex size-[26px] items-center justify-center rounded-full border-2 border-outline bg-marigold text-on-marigold transition-transform duration-200 ease-stall"
                        :class="useWallet ? 'translate-x-[23px]' : ''"
                    >
                        <Icon v-if="useWallet" name="check" :size="16" />
                    </span>
                </span>
            </span>
        </OptionCard>
        <OptionCard v-else :selected="model === 'wallet'" :disabled="tooLow">
            <input
                v-model="model"
                type="radio"
                name="payment_method"
                value="wallet"
                class="sr-only"
                :disabled="tooLow"
                data-test="pay-wallet"
            />
            <span
                aria-hidden="true"
                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-chip text-ink"
            >
                <Icon name="check" :size="22" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-extrabold text-ink">Wallet</span>
                <span class="block text-[15px] text-body">
                    <template v-if="tooLow">
                        Balance {{ props.balance }}. You need
                        {{ formatCents(shortfall) }} more.
                    </template>
                    <template v-else>Balance {{ props.balance }}</template>
                </span>
            </span>
        </OptionCard>
        <Link
            v-if="tooLow && !switchShown"
            :href="showWallet()"
            class="inline-flex min-h-11 items-center self-start rounded-lg font-bold text-link underline underline-offset-4"
            data-test="top-up-link"
        >
            Top up your wallet
        </Link>

        <OptionCard
            v-for="method in props.methods"
            :key="method"
            :selected="model === method"
            :disabled="belowMinimum"
        >
            <input
                v-model="model"
                type="radio"
                name="payment_method"
                class="sr-only"
                :value="method"
                :disabled="belowMinimum"
                :data-test="`pay-${method}`"
            />
            <span class="min-w-0 flex-1">
                <span class="sr-only">{{ hosted[method].name }}</span>
                <component :is="hosted[method].component" />
                <span class="block text-[15px] text-body">
                    {{ noteFor(method) }}
                </span>
            </span>
        </OptionCard>
        <p
            v-if="props.methods.length > 0 && belowMinimum"
            class="text-[15px] text-body"
        >
            Stripe and PayPal payments start at $0.50.
        </p>
    </fieldset>
</template>
