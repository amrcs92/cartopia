<script setup lang="ts">
import { computed } from 'vue';
import SectionHeading from '@/common/stall/SectionHeading.vue';
import { deliverySentence } from '@/modules/home/shipping';
import type { ShippingMethod } from '@/types';

// "How shopping works": three numbered steps. The second is built from the
// real shipping methods and their fees.
const props = defineProps<{
    shippingMethods: ShippingMethod[];
}>();

const deliveryLine = computed(() => deliverySentence(props.shippingMethods));

const steps = computed(() => [
    {
        title: 'Fill your basket',
        body: 'Browse as a guest if you like. Log in later and your basket comes with you.',
    },
    { title: 'Choose how to get it', body: deliveryLine.value },
    {
        title: 'Pay from your wallet',
        body: 'The total comes off your balance and the order is saved with a receipt.',
    },
]);
</script>

<template>
    <section aria-labelledby="how-it-works" class="flex flex-col gap-6">
        <SectionHeading id="how-it-works" title="How shopping works" />
        <ol class="grid gap-4 sm:gap-6 lg:grid-cols-3">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="flex flex-col gap-3 rounded-[24px] border-[1.5px] border-line bg-surface p-[22px] sm:p-[26px]"
            >
                <span
                    aria-hidden="true"
                    class="inline-flex size-[52px] items-center justify-center rounded-full border-[2.5px] border-outline bg-marigold font-serif text-[24px] text-on-marigold"
                >
                    {{ index + 1 }}
                </span>
                <h3
                    class="font-serif text-[20px] leading-[1.2] font-normal text-ink sm:text-[24px]"
                >
                    {{ step.title }}
                </h3>
                <p class="text-base leading-relaxed text-body">
                    {{ step.body }}
                </p>
            </li>
        </ol>
    </section>
</template>
