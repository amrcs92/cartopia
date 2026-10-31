<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Chip from '@/common/stall/Chip.vue';
import StatusChip from '@/common/stall/StatusChip.vue';
import OrderThumbnails from '@/modules/orders/OrderThumbnails.vue';
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import { show } from '@/routes/orders';
import type { Order } from '@/types';

// One order in the list: photos, number, date, items, delivery, how it was
// paid, its status and the total.
const props = defineProps<{
    order: Order;
}>();
</script>

<template>
    <li
        class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[22px] border-[2.5px] border-outline bg-surface p-4 shadow-card sm:p-5"
        data-test="order-row"
    >
        <OrderThumbnails :thumbnails="props.order.thumbnails" />

        <div class="min-w-0 flex-1 basis-48">
            <Link
                :href="show(props.order.id)"
                class="inline-flex min-h-11 items-center rounded-lg font-serif text-[22px] text-ink underline-offset-4 hover:underline"
            >
                Order #{{ props.order.id }}
            </Link>
            <p class="text-[15px] text-body">
                {{ formatOrderDate(props.order.placed_at) }} ·
                {{ props.order.items.length }}
                {{ props.order.items.length === 1 ? 'item' : 'items' }} ·
                {{ props.order.shipping_method }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <Chip tone="tint-3">Paid with wallet</Chip>
                <StatusChip class="capitalize">{{
                    props.order.status
                }}</StatusChip>
            </div>
        </div>

        <p
            class="ml-auto font-serif text-[26px] text-ink tabular-nums"
            data-test="order-row-total"
        >
            {{ props.order.total }}
        </p>
    </li>
</template>
