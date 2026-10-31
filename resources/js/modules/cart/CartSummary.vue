<script setup lang="ts">
import Card from '@/common/card/Card.vue';
import CardContent from '@/common/card/CardContent.vue';
import CardHeader from '@/common/card/CardHeader.vue';
import CardTitle from '@/common/card/CardTitle.vue';
import type { Cart } from '@/types';

const props = defineProps<{
    cart: Cart;
}>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Order summary</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Items</dt>
                    <dd class="tabular-nums" data-test="cart-total-quantity">
                        {{ props.cart.total_quantity }}
                    </dd>
                </div>
                <div class="flex justify-between text-base font-semibold">
                    <dt>Subtotal</dt>
                    <dd class="tabular-nums" data-test="cart-subtotal">
                        {{ props.cart.subtotal }}
                    </dd>
                </div>
            </dl>
            <slot />
        </CardContent>
    </Card>
</template>
