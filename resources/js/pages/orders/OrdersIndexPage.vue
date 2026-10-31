<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/common/button/Button.vue';
import Pagination from '@/common/pagination/Pagination.vue';
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import { show } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import type { Order, Paginated } from '@/types';

const props = defineProps<{
    orders: Paginated<Order>;
}>();
</script>

<template>
    <Head title="Your orders" />

    <div class="space-y-6">
        <h1 class="text-2xl font-semibold">Your orders</h1>

        <div
            v-if="props.orders.data.length === 0"
            class="space-y-4 rounded-xl border border-dashed p-10 text-center"
            data-test="no-orders"
        >
            <p class="text-muted-foreground">You have no orders yet.</p>
            <Button as-child>
                <Link :href="productsIndex()">Start shopping</Link>
            </Button>
        </div>

        <ul v-else class="divide-y rounded-xl border px-4">
            <li
                v-for="order in props.orders.data"
                :key="order.id"
                class="flex flex-wrap items-center justify-between gap-2 py-4"
                data-test="order-row"
            >
                <div>
                    <Link
                        :href="show(order.id)"
                        class="font-medium hover:underline"
                    >
                        Order #{{ order.id }}
                    </Link>
                    <p class="text-sm text-muted-foreground">
                        {{ formatOrderDate(order.placed_at) }} ·
                        {{ order.items.length }}
                        {{ order.items.length === 1 ? 'item' : 'items' }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="font-semibold tabular-nums">{{ order.total }}</p>
                    <p class="text-sm text-muted-foreground capitalize">
                        {{ order.status }}
                    </p>
                </div>
            </li>
        </ul>

        <Pagination :links="props.orders.meta.links" />
    </div>
</template>
