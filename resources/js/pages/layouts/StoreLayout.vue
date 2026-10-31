<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';
import Breadcrumbs from '@/common/app/Breadcrumbs.vue';
import FlashToast from '@/common/flash-toast/FlashToast.vue';
import AddedToBasketPanel from '@/modules/cart/AddedToBasketPanel.vue';
import Awning from '@/modules/layout/Awning.vue';
import PhoneHeader from '@/modules/layout/PhoneHeader.vue';
import PhoneMenu from '@/modules/layout/PhoneMenu.vue';
import StoreFooter from '@/modules/layout/StoreFooter.vue';
import StoreHeader from '@/modules/layout/StoreHeader.vue';
import type { BreadcrumbItem } from '@/types';

// The store frame: the announcement bar, the desktop header or (below 640px)
// the phone header and its menu, the awning, the page and the footer. Only one
// header is rendered at a time, so each landmark and test hook appears once.
const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const isPhone = useMediaQuery('(max-width: 639.98px)');
const menuOpen = ref(false);

watch(isPhone, () => {
    menuOpen.value = false;
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-paper text-ink">
        <a
            href="#main"
            class="sr-only z-50 rounded-xl bg-surface px-4 py-2 font-bold text-ink focus:not-sr-only focus:absolute focus:top-3 focus:left-3"
        >
            Skip to content
        </a>

        <p
            class="bg-announce px-4 py-2 text-center text-[13px] font-bold text-announce-ink sm:text-sm"
        >
            <span class="sm:hidden">
                Free pickup. New accounts get $100 credit.
            </span>
            <span class="max-sm:hidden">
                Free pickup on every order. New accounts start with $100 of
                credit.
            </span>
        </p>

        <template v-if="isPhone">
            <PhoneHeader @open-menu="menuOpen = true" />
            <PhoneMenu :open="menuOpen" @close="menuOpen = false" />
        </template>
        <StoreHeader v-else />

        <Awning />

        <main
            id="main"
            tabindex="-1"
            class="mx-auto w-full max-w-[1200px] flex-1 px-4 py-6 outline-none sm:px-6"
        >
            <Breadcrumbs
                v-if="breadcrumbs.length > 0"
                :breadcrumbs="breadcrumbs"
                class="mb-4"
            />
            <slot />
        </main>

        <StoreFooter />

        <AddedToBasketPanel />
        <FlashToast />
    </div>
</template>
