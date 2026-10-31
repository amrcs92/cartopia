<script setup lang="ts">
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import type { WalletTransaction } from '@/types';

// The history as a table that scrolls sideways inside its own box on phones,
// so the page itself never does.
const props = defineProps<{
    transactions: WalletTransaction[];
}>();
</script>

<template>
    <p
        v-if="props.transactions.length === 0"
        class="rounded-[22px] border-2 border-dashed border-line-strong p-10 text-center text-body"
        data-test="no-transactions"
    >
        No transactions yet.
    </p>

    <div
        v-else
        class="overflow-x-auto rounded-[22px] border-[2.5px] border-outline bg-surface"
        tabindex="0"
        role="region"
        aria-label="Transaction history"
    >
        <table class="w-full min-w-[34rem] border-collapse text-left">
            <thead>
                <tr class="border-b-2 border-outline bg-well text-ink">
                    <th scope="col" class="px-4 py-3 text-sm font-extrabold">
                        Date
                    </th>
                    <th scope="col" class="px-4 py-3 text-sm font-extrabold">
                        What
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 text-right text-sm font-extrabold"
                    >
                        Amount
                    </th>
                    <th
                        scope="col"
                        class="px-4 py-3 text-right text-sm font-extrabold"
                    >
                        Balance after
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="transaction in props.transactions"
                    :key="transaction.id"
                    class="border-b border-line last:border-b-0"
                    data-test="ledger-row"
                >
                    <td class="px-4 py-3 whitespace-nowrap text-body">
                        {{ formatOrderDate(transaction.created_at) }}
                    </td>
                    <td class="px-4 py-3 text-ink">
                        <span class="font-bold">{{
                            transaction.type_label
                        }}</span>
                        <span v-if="transaction.description" class="text-body">
                            · {{ transaction.description }}
                        </span>
                    </td>
                    <td
                        class="px-4 py-3 text-right font-extrabold whitespace-nowrap tabular-nums"
                        :class="
                            transaction.amount.startsWith('+')
                                ? 'text-chip-ink'
                                : 'text-ink'
                        "
                        data-test="ledger-amount"
                    >
                        {{ transaction.amount }}
                    </td>
                    <td
                        class="px-4 py-3 text-right whitespace-nowrap text-body tabular-nums"
                    >
                        {{ transaction.balance_after }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
