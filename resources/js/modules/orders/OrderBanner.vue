<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';

// The "Order placed." banner. Checkout delivers it as Inertia's one-shot
// flash (`orderPlaced`), which is not kept in history state, so going Back to
// the receipt does not show it again. It is read when the page opens and when
// the router's `flash` event arrives.
const page = usePage();
const message = ref<string | null>(
    (page.flash as { orderPlaced?: string } | undefined)?.orderPlaced ?? null,
);

let stopListening: (() => void) | undefined;

onMounted(() => {
    stopListening = router.on('flash', (event) => {
        const value = (event as CustomEvent).detail?.flash?.orderPlaced as
            | string
            | undefined;

        if (value) {
            message.value = value;
        }
    });
});

onBeforeUnmount(() => stopListening?.());
</script>

<template>
    <div
        v-if="message"
        role="status"
        class="flex items-center gap-4 rounded-[18px] border-[2.5px] border-outline bg-chip p-4 text-chip-ink"
        data-test="order-placed"
    >
        <span
            aria-hidden="true"
            class="inline-flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-outline bg-surface text-ink"
        >
            <Icon name="check" :size="24" />
        </span>
        <div>
            <p class="font-serif text-[22px] leading-tight">{{ message }}</p>
            <p class="text-[15px]">Your receipt is below.</p>
        </div>
    </div>
</template>
