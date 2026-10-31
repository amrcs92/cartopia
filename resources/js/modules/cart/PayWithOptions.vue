<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import OptionCard from '@/common/stall/OptionCard.vue';
import { formatCents } from '@/modules/cart/money';
import { show as showWallet } from '@/routes/wallet';

// "Pay with": the wallet as an option card (more cards can be added later
// without changing this layout). When the balance cannot cover the total the
// card is disabled and says how much more is needed, with a top-up link.
const props = defineProps<{
    balance: string;
    balanceCents: number;
    totalCents: number;
}>();

const shortfall = computed(() => props.totalCents - props.balanceCents);
const tooLow = computed(() => shortfall.value > 0);
</script>

<template>
    <fieldset class="flex flex-col gap-3">
        <legend class="mb-3 font-serif text-[20px] text-ink">Pay with</legend>
        <OptionCard :selected="!tooLow" :disabled="tooLow">
            <input
                type="radio"
                name="payment"
                value="wallet"
                class="sr-only"
                :checked="!tooLow"
                :disabled="tooLow"
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
            v-if="tooLow"
            :href="showWallet()"
            class="inline-flex min-h-11 items-center self-start rounded-lg font-bold text-link underline underline-offset-4"
            data-test="top-up-link"
        >
            Top up your wallet
        </Link>
    </fieldset>
</template>
