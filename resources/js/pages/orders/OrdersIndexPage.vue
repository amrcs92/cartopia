<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';
import Pagination from '@/common/pagination/Pagination.vue';
import StallButton from '@/common/stall/StallButton.vue';
import OrderRow from '@/modules/orders/OrderRow.vue';
import { index as productsIndex } from '@/routes/products';
import type { Order, Paginated } from '@/types';

const props = defineProps<{
    orders: Paginated<Order>;
}>();
</script>

<template>
    <Head title="Your orders" />

    <div class="space-y-6">
        <h1 class="font-serif text-[32px] leading-tight text-ink lg:text-5xl">
            Your orders
        </h1>

        <div
            v-if="props.orders.data.length === 0"
            class="flex flex-col items-center gap-5 pt-6 text-center"
            data-test="no-orders"
        >
            <span
                aria-hidden="true"
                class="inline-flex size-28 items-center justify-center rounded-full border-[2.5px] border-outline bg-field text-ink"
            >
                <Icon name="basket" :size="56" />
            </span>
            <h2 class="font-serif text-[28px] leading-tight text-ink">
                Nothing bagged yet.
            </h2>
            <p class="text-body">
                Orders you place will be kept here, with the prices you paid.
            </p>
            <StallButton
                as="link"
                variant="marigold"
                size="lg"
                :href="productsIndex().url"
            >
                Browse the shop
            </StallButton>
        </div>

        <ul v-else class="flex flex-col gap-5">
            <OrderRow
                v-for="order in props.orders.data"
                :key="order.id"
                :order="order"
            />
        </ul>

        <Pagination :links="props.orders.meta.links" />
    </div>
</template>
