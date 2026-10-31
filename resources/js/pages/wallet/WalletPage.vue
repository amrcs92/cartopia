<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Card from '@/common/card/Card.vue';
import CardContent from '@/common/card/CardContent.vue';
import CardHeader from '@/common/card/CardHeader.vue';
import CardTitle from '@/common/card/CardTitle.vue';
import Pagination from '@/common/pagination/Pagination.vue';
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

    <div class="space-y-6">
        <h1 class="text-2xl font-semibold">Your wallet</h1>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Balance</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-3xl font-semibold tabular-nums"
                        data-test="wallet-balance"
                    >
                        {{ props.balance }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Add credit</CardTitle>
                </CardHeader>
                <CardContent>
                    <TopUpForm />
                </CardContent>
            </Card>
        </div>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Transaction history</h2>
            <LedgerTable :transactions="props.transactions.data" />
            <Pagination :links="props.transactions.meta.links" />
        </section>
    </div>
</template>
