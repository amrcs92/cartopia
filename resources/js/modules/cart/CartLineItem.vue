<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import IconButton from '@/common/stall/IconButton.vue';
import QuantityStepper from '@/modules/cart/QuantityStepper.vue';
import { removeCartItem, updateCartItem } from '@/modules/cart/useCartActions';
import { show } from '@/routes/products';
import type { CartLine } from '@/types';

// One basket line: photo, name and unit price, the quantity stepper, the line
// total and a remove button. A product the shop stopped selling stays in the
// basket marked "No longer available": no price, no quantity changes, but it
// can still be removed.
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
        class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-[22px] border-[1.5px] border-line bg-surface p-3 sm:p-4"
        :data-test="`cart-line-${props.line.product.id}`"
    >
        <component
            :is="props.line.available ? Link : 'span'"
            v-bind="
                props.line.available ? { href: show(props.line.product) } : {}
            "
            tabindex="-1"
            aria-hidden="true"
            class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-well p-2 sm:size-24"
            :class="{ 'opacity-50 grayscale': !props.line.available }"
        >
            <img
                v-if="props.line.product.image_url"
                :src="props.line.product.image_url"
                alt=""
                class="max-h-full max-w-full object-contain mix-blend-multiply"
            />
            <Icon
                v-else
                name="basket"
                :size="32"
                class="text-on-marigold opacity-30"
            />
        </component>

        <div class="min-w-40 flex-1 space-y-1">
            <Link
                v-if="props.line.available"
                :href="show(props.line.product)"
                class="rounded-md font-serif text-[21px] leading-tight wrap-anywhere text-ink underline-offset-4 hover:underline"
            >
                {{ props.line.product.name }}
            </Link>
            <span
                v-else
                class="block font-serif text-[21px] leading-tight wrap-anywhere text-ink"
            >
                {{ props.line.product.name }}
            </span>
            <p v-if="props.line.available" class="text-body">
                {{ props.line.unit_price }} each
            </p>
            <p v-else class="font-bold text-body" data-test="line-unavailable">
                No longer available
            </p>
        </div>

        <QuantityStepper
            :model-value="quantity"
            :label="props.line.product.name"
            :disabled="!props.line.available"
            @update:model-value="onQuantity"
        />

        <p class="w-24 text-right font-serif text-[22px] text-ink tabular-nums">
            <template v-if="props.line.available">
                {{ props.line.line_total }}
            </template>
        </p>

        <IconButton
            :label="`Remove ${props.line.product.name} from basket`"
            icon="remove"
            @click="removeCartItem(props.line.product.id)"
        />
    </li>
</template>
