<script setup lang="ts">
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import type { WalletTransaction } from '@/types';

const props = defineProps<{
    transactions: WalletTransaction[];
}>();
</script>

<template>
    <p
        v-if="props.transactions.length === 0"
        class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        data-test="no-transactions"
    >
        No transactions yet.
    </p>

    <ul v-else class="divide-y rounded-xl border px-4">
        <li
            v-for="transaction in props.transactions"
            :key="transaction.id"
            class="flex flex-wrap items-center justify-between gap-2 py-3"
            data-test="ledger-row"
        >
            <div>
                <p class="font-medium">{{ transaction.type_label }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ formatOrderDate(transaction.created_at) }}
                    <template v-if="transaction.description">
                        · {{ transaction.description }}
                    </template>
                </p>
            </div>
            <div class="text-right tabular-nums">
                <p
                    class="font-semibold"
                    :class="
                        transaction.amount.startsWith('+')
                            ? 'text-green-600 dark:text-green-400'
                            : ''
                    "
                    data-test="ledger-amount"
                >
                    {{ transaction.amount }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Balance {{ transaction.balance_after }}
                </p>
            </div>
        </li>
    </ul>
</template>
