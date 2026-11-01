<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';
import StallButton from '@/common/stall/StallButton.vue';
import StatusChip from '@/common/stall/StatusChip.vue';
import OrderBanner from '@/modules/orders/OrderBanner.vue';
import { formatOrderDate } from '@/modules/orders/formatOrderDate';
import { index as ordersIndex } from '@/routes/orders';
import { show as showProduct } from '@/routes/products';
import type { Order } from '@/types';

// The receipt: a paper slip with a torn bottom edge. The slip is wrapped so
// the hard shadow follows the torn shape (a drop-shadow with no blur).
const props = defineProps<{
    order: Order;
    balance_after: string | null;
}>();

const methodIcon = {
    wallet: 'wallet',
    stripe: 'card',
    paypal: 'external-link',
} as const;

// The wallet line keeps a stable hook for tests.
const lineTestId = (label: string) =>
    label === 'Wallet balance after' ? 'balance-after' : undefined;
</script>

<template>
    <Head :title="`Order #${props.order.id}`" />

    <div class="mx-auto flex max-w-xl flex-col gap-6">
        <OrderBanner />

        <div class="drop-shadow-[5px_5px_0_var(--shadow-green)]">
            <article
                class="torn-edge border-[2.5px] border-b-0 border-outline bg-cream px-5 pt-6 pb-12 sm:px-8"
                aria-labelledby="receipt-title"
            >
                <header
                    class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-dashed border-line-strong pb-4"
                >
                    <div>
                        <h1
                            id="receipt-title"
                            class="font-serif text-[30px] leading-tight text-ink"
                        >
                            Order #{{ props.order.id }}
                        </h1>
                        <p class="text-[15px] text-body">
                            Placed {{ formatOrderDate(props.order.placed_at) }}
                        </p>
                    </div>
                    <StatusChip class="capitalize" data-test="order-status">{{
                        props.order.status
                    }}</StatusChip>
                </header>

                <ul class="divide-y-2 divide-dashed divide-line-strong">
                    <li
                        v-for="(item, index) in props.order.items"
                        :key="index"
                        class="flex items-start justify-between gap-4 py-4"
                        data-test="order-item"
                    >
                        <div class="min-w-0">
                            <Link
                                v-if="item.product_slug"
                                :href="showProduct(item.product_slug)"
                                class="inline-flex min-h-11 items-center font-bold text-ink underline-offset-4 hover:underline"
                            >
                                {{ item.product_name }}
                            </Link>
                            <span
                                v-else
                                class="inline-flex min-h-11 items-center font-bold text-ink"
                            >
                                {{ item.product_name }}
                            </span>
                            <p class="text-[15px] text-body tabular-nums">
                                {{ item.quantity }} × {{ item.unit_price }}
                            </p>
                        </div>
                        <p class="pt-2.5 font-bold text-ink tabular-nums">
                            {{ item.line_total }}
                        </p>
                    </li>
                </ul>

                <dl
                    class="space-y-2 border-t-2 border-dashed border-line-strong pt-4"
                >
                    <div class="flex justify-between text-body">
                        <dt>Subtotal</dt>
                        <dd class="tabular-nums">{{ props.order.subtotal }}</dd>
                    </div>
                    <div class="flex justify-between text-body">
                        <dt>Delivery ({{ props.order.shipping_method }})</dt>
                        <dd class="tabular-nums">
                            {{ props.order.shipping_fee }}
                        </dd>
                    </div>
                    <div
                        class="flex items-baseline justify-between font-serif text-[26px] text-ink"
                    >
                        <dt>Total</dt>
                        <dd class="tabular-nums" data-test="order-total">
                            {{ props.order.total }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 pt-2 text-body">
                        <dt>Paid with</dt>
                        <dd class="flex items-center gap-2 text-right">
                            <Icon
                                :name="methodIcon[props.order.payment.method]"
                                :size="18"
                            />
                            {{ props.order.payment.paid_with }}
                        </dd>
                    </div>
                    <div
                        v-if="props.order.payment.reference"
                        class="flex justify-between gap-4 text-body"
                    >
                        <dt>Payment ref.</dt>
                        <dd
                            class="text-right break-all"
                            :title="props.order.payment.reference.full"
                            :aria-label="props.order.payment.reference.full"
                            data-test="payment-ref"
                        >
                            {{ props.order.payment.reference.short }}
                        </dd>
                    </div>
                    <div
                        v-for="line in props.order.payment.slip_lines"
                        :key="line.label"
                        class="flex justify-between text-body"
                    >
                        <dt>{{ line.label }}</dt>
                        <dd
                            class="tabular-nums"
                            :data-test="lineTestId(line.label)"
                        >
                            {{ line.value }}
                        </dd>
                    </div>
                </dl>
            </article>
        </div>

        <div>
            <StallButton as="link" variant="outline" :href="ordersIndex().url">
                Back to orders
            </StallButton>
        </div>
    </div>
</template>
