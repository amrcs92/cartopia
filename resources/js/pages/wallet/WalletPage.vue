<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pagination from '@/common/pagination/Pagination.vue';
import Chalkboard from '@/common/stall/Chalkboard.vue';
import SectionHeading from '@/common/stall/SectionHeading.vue';
import LedgerTable from '@/modules/wallet/LedgerTable.vue';
import TopUpForm from '@/modules/wallet/TopUpForm.vue';
import type { Paginated, WalletTransaction } from '@/types';

const props = defineProps<{
    balance: string;
    transactions: Paginated<WalletTransaction>;
}>();
</script>

<template>
    <Head title="Your wallet" />

    <div class="space-y-8">
        <h1 class="font-serif text-[32px] leading-tight text-ink lg:text-5xl">
            Your wallet
        </h1>

        <div class="grid items-start gap-8 lg:grid-cols-2">
            <Chalkboard>
                <p class="text-[22px] text-chalk/80">Your balance</p>
                <p
                    class="text-[56px] leading-none text-marigold tabular-nums lg:text-[72px]"
                    data-test="wallet-balance"
                >
                    {{ props.balance }}
                </p>
                <p class="mt-3 text-[22px] text-chalk/80">
                    Pays at the till. Top up any time.
                </p>
            </Chalkboard>

            <section
                aria-labelledby="add-credit"
                class="flex flex-col gap-4 rounded-[26px] border-[2.5px] border-outline bg-surface p-5 shadow-card lg:p-6"
            >
                <h2 id="add-credit" class="font-serif text-[26px] text-ink">
                    Add credit
                </h2>
                <TopUpForm />
            </section>
        </div>

        <section class="space-y-4">
            <SectionHeading title="Transaction history" />
            <LedgerTable :transactions="props.transactions.data" />
            <Pagination :links="props.transactions.meta.links" />
        </section>
    </div>
</template>
