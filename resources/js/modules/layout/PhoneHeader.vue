<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/common/brand/AppLogo.vue';
import Icon from '@/common/icon/Icon.vue';
import IconButton from '@/common/stall/IconButton.vue';
import { pressTransition } from '@/common/stall/press';
import { home } from '@/routes';
import { show as showCart } from '@/routes/cart';
import StallSearch from './StallSearch.vue';
import { useStoreChrome } from './useStoreChrome';

// The phone header (below 640px): the menu button, the centred logo and the
// basket with its count, then a full-width search bar.
const emit = defineEmits<{
    openMenu: [];
}>();

const { basketCount } = useStoreChrome();

const basketLabel = computed(() =>
    basketCount.value === 1
        ? 'Basket, 1 item'
        : `Basket, ${basketCount.value} items`,
);
</script>

<template>
    <header class="bg-paper px-4 pt-3 pb-3.5">
        <div class="grid grid-cols-[44px_1fr_44px] items-center gap-3">
            <IconButton
                label="Open menu"
                icon="menu"
                aria-haspopup="dialog"
                @click="emit('openMenu')"
            />
            <Link
                :href="home()"
                aria-label="Cartopia home"
                class="justify-self-center rounded-xl"
            >
                <AppLogo size="phone" />
            </Link>
            <Link
                :href="showCart()"
                :aria-label="basketLabel"
                class="relative inline-flex size-11 items-center justify-center rounded-xl border-2 border-outline bg-surface text-ink hover:bg-field"
                :class="pressTransition"
            >
                <Icon name="basket" :size="22" />
                <span
                    v-if="basketCount > 0"
                    aria-hidden="true"
                    data-test="cart-badge"
                    class="absolute -top-2 -right-2 inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full border-2 border-outline bg-marigold px-1 text-xs font-extrabold text-on-marigold tabular-nums dark:bg-green dark:text-primary-foreground"
                >
                    {{ basketCount }}
                </span>
            </Link>
        </div>
        <StallSearch class="mt-3" />
    </header>
</template>
