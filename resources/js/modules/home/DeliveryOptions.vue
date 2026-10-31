<script setup lang="ts">
import Icon from '@/common/icon/Icon.vue';
import SectionHeading from '@/common/stall/SectionHeading.vue';
import type { ShippingMethod } from '@/types';

// "Getting it home": one card per shipping method. A free method is pickup,
// a paid one is delivery; the fee reads "Free" when it is zero.
const props = defineProps<{
    shippingMethods: ShippingMethod[];
}>();

function isPickup(method: ShippingMethod): boolean {
    return method.fee_cents === 0;
}
</script>

<template>
    <section
        id="delivery"
        aria-labelledby="delivery-title"
        class="flex scroll-mt-6 flex-col gap-6"
    >
        <SectionHeading id="delivery-title" title="Getting it home" />
        <ul v-if="props.shippingMethods.length > 0" class="flex flex-col gap-4">
            <li
                v-for="method in props.shippingMethods"
                :key="method.id"
                class="flex items-center gap-4 rounded-[22px] border-[2.5px] border-outline bg-surface p-4 sm:p-5"
            >
                <span
                    aria-hidden="true"
                    class="inline-flex size-14 shrink-0 items-center justify-center rounded-2xl text-ink"
                    :class="isPickup(method) ? 'bg-chip' : 'bg-tint-3'"
                >
                    <Icon
                        :name="isPickup(method) ? 'pickup' : 'delivery'"
                        :size="28"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <h3
                        class="text-[18px] font-extrabold text-ink sm:text-[19px]"
                    >
                        {{ method.name }}
                    </h3>
                    <p class="text-[15px] text-body">
                        {{
                            isPickup(method)
                                ? 'Collect your order yourself.'
                                : 'Delivered to your address.'
                        }}
                    </p>
                </div>
                <p
                    class="shrink-0 font-serif text-[22px] text-ink tabular-nums sm:text-[26px]"
                >
                    {{ isPickup(method) ? 'Free' : method.fee }}
                </p>
            </li>
        </ul>
        <p v-else class="text-body">
            Delivery options are on their way. Check back soon.
        </p>
    </section>
</template>
