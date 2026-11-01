<script setup lang="ts">
import { useElementSize, useMediaQuery, useWindowScroll } from '@vueuse/core';
import {
    computed,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
    watchEffect,
} from 'vue';
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

// The header stays pinned to the top of the window. Once the page has
// scrolled past the announcement bar the header floats over the page, so it
// gets a bottom rule and a soft shadow. Its height goes in
// --store-header-h on the root, for the scroll padding (in-page jumps land
// below it) and for anything else that sticks under it.
const headerBar = useTemplateRef('headerBar');
const { height: headerHeight } = useElementSize(headerBar, undefined, {
    box: 'border-box',
});
const { y: scrollY } = useWindowScroll();

// A sticky element's offsetTop follows it as it sticks, so the point it
// starts floating is taken from the bottom of the announcement bar above it.
const announcement = useTemplateRef('announcement');
const headerFloating = computed(
    () =>
        announcement.value !== null &&
        scrollY.value >
            announcement.value.offsetTop + announcement.value.offsetHeight,
);

watchEffect(() => {
    if (typeof document !== 'undefined' && headerHeight.value > 0) {
        document.documentElement.style.setProperty(
            '--store-header-h',
            `${headerHeight.value}px`,
        );
    }
});

onUnmounted(() => {
    document.documentElement.style.removeProperty('--store-header-h');
});

// On a phone the checkout bar is pinned over the bottom of the screen; the
// root's bottom padding (only while the bar is on the page) keeps the
// footer's last row clear of it.
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-paper text-ink max-sm:has-[[data-test=checkout-bar]]:pb-20"
    >
        <a
            href="#main"
            class="sr-only z-50 rounded-xl bg-surface px-4 py-2 font-bold text-ink focus:not-sr-only focus:absolute focus:top-3 focus:left-3"
        >
            Skip to content
        </a>

        <p
            ref="announcement"
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

        <div
            ref="headerBar"
            data-test="header-bar"
            :data-floating="headerFloating"
            class="sticky top-0 z-30 transition-shadow duration-200 ease-stall"
            :class="
                headerFloating &&
                'shadow-[0_2.5px_0_var(--outline),0_10px_22px_-12px_rgb(0_0_0/0.45)]'
            "
        >
            <PhoneHeader v-if="isPhone" @open-menu="menuOpen = true" />
            <StoreHeader v-else />
        </div>
        <PhoneMenu v-if="isPhone" :open="menuOpen" @close="menuOpen = false" />

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
