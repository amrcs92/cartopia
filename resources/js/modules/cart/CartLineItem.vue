<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import Button from '@/common/button/Button.vue';
import QuantityStepper from '@/modules/cart/QuantityStepper.vue';
import { removeCartItem, updateCartItem } from '@/modules/cart/useCartActions';
import { show } from '@/routes/products';
import type { CartLine } from '@/types';

const props = defineProps<{
    line: CartLine;
}>();

const quantity = ref(props.line.quantity);

watch(
    () => props.line.quantity,
    (value) => {
        quantity.value = value;
    },
);

function onQuantity(value: number): void {
    quantity.value = value;

    if (value !== props.line.quantity) {
        updateCartItem(props.line.product.id, value);
    }
}
</script>

<template>
    <li
        class="flex flex-wrap items-center gap-4 py-4"
        :data-test="`cart-line-${props.line.product.id}`"
    >
        <Link
            :href="show(props.line.product)"
            class="size-20 shrink-0 overflow-hidden rounded-md border bg-muted"
        >
            <img
                v-if="props.line.product.image_url"
                :src="props.line.product.image_url"
                :alt="props.line.product.name"
                class="size-full object-cover"
            />
            <span
                v-else
                class="flex size-full items-center justify-center text-muted-foreground"
            >
                <ImageOff class="size-6" aria-hidden="true" />
            </span>
        </Link>

        <div class="min-w-40 flex-1 space-y-1">
            <Link
                :href="show(props.line.product)"
                class="font-medium hover:underline"
            >
                {{ props.line.product.name }}
            </Link>
            <p class="text-sm text-muted-foreground">
                {{ props.line.unit_price }} each
            </p>
        </div>

        <QuantityStepper
            :model-value="quantity"
            :label="props.line.product.name"
            @update:model-value="onQuantity"
        />

        <p class="w-24 text-right font-semibold tabular-nums">
            {{ props.line.line_total }}
        </p>

        <Button
            variant="ghost"
            size="icon"
            type="button"
            :aria-label="`Remove ${props.line.product.name} from cart`"
            @click="removeCartItem(props.line.product.id)"
        >
            <Trash2 class="size-4" aria-hidden="true" />
        </Button>
    </li>
</template>
