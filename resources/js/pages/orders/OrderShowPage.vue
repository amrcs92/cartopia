<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/common/button/Button.vue';
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import { index as ordersIndex } from '@/routes/orders';
import { show as showProduct } from '@/routes/products';
import type { Order } from '@/types';

const props = defineProps<{
    order: Order;
}>();
</script>

<template>
    <Head :title="`Order #${props.order.id}`" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="text-2xl font-semibold">Order #{{ props.order.id }}</h1>
            <p class="text-sm text-muted-foreground">
                Placed {{ formatOrderDate(props.order.placed_at) }} ·
                <span class="capitalize" data-test="order-status">{{
                    props.order.status
                }}</span>
            </p>
        </div>

        <ul class="divide-y rounded-xl border px-4">
            <li
                v-for="(item, index) in props.order.items"
                :key="index"
                class="flex items-center justify-between gap-4 py-4"
                data-test="order-item"
            >
                <div>
                    <Link
                        v-if="item.product_slug"
                        :href="showProduct(item.product_slug)"
                        class="font-medium hover:underline"
                    >
                        {{ item.product_name }}
                    </Link>
                    <span v-else class="font-medium">
                        {{ item.product_name }}
                    </span>
                    <p class="text-sm text-muted-foreground tabular-nums">
                        {{ item.quantity }} × {{ item.unit_price }}
                    </p>
                </div>
                <p class="font-medium tabular-nums">{{ item.line_total }}</p>
            </li>
        </ul>

        <dl class="ml-auto max-w-xs space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-muted-foreground">Subtotal</dt>
                <dd class="tabular-nums">{{ props.order.subtotal }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-muted-foreground">
                    Shipping ({{ props.order.shipping_method }})
                </dt>
                <dd class="tabular-nums">{{ props.order.shipping_fee }}</dd>
            </div>
            <div class="flex justify-between text-base font-semibold">
                <dt>Total</dt>
                <dd class="tabular-nums" data-test="order-total">
                    {{ props.order.total }}
                </dd>
            </div>
        </dl>

        <Button variant="outline" as-child>
            <Link :href="ordersIndex()">Back to orders</Link>
        </Button>
    </div>
</template>
