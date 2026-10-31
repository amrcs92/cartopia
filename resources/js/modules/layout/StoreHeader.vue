<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/common/brand/AppLogo.vue';
import Icon from '@/common/icon/Icon.vue';
import { press, pressTransition, sticker } from '@/common/stall/press';
import ThemeToggle from '@/common/theme/ThemeToggle.vue';
import { cn } from '@/lib/utils';
import { home, login } from '@/routes';
import { show as showCart } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { show as showWallet } from '@/routes/wallet';
import StallSearch from './StallSearch.vue';
import { useStoreChrome } from './useStoreChrome';
import type { StoreSection } from './useStoreChrome';

// The desktop and tablet header: logo, search, the section links, the theme
// button and the basket. The current section gets a surface background and a
// marigold underline (and aria-current), the wallet chip turns marigold.
const { user, basketCount, section } = useStoreChrome();

function navClass(target: StoreSection): string {
    return cn(
        'inline-flex min-h-11 items-center rounded-xl px-3.5 text-base font-bold text-ink transition-colors duration-200 ease-stall hover:bg-surface',
        section.value === target &&
            'bg-surface shadow-[inset_0_-3px_0_var(--marigold)]',
    );
}

function current(target: StoreSection): 'page' | undefined {
    return section.value === target ? 'page' : undefined;
}

const basketClass = cn(
    'inline-flex min-h-11 items-center gap-2 rounded-xl px-4 text-base font-extrabold whitespace-nowrap',
    pressTransition,
    sticker.md,
    press.md,
    'bg-green text-primary-foreground hover:bg-green/90 dark:bg-marigold dark:text-on-marigold dark:hover:bg-marigold/90',
);
</script>

<template>
    <header class="bg-paper">
        <div
            class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-5 px-6 py-[18px]"
        >
            <Link :href="home()" aria-label="Cartopia home" class="rounded-xl">
                <AppLogo />
            </Link>

            <StallSearch class="min-w-64 flex-1 basis-80" />

            <nav aria-label="Main" class="ml-auto flex items-center gap-2">
                <Link
                    :href="productsIndex()"
                    :class="navClass('shop')"
                    :aria-current="current('shop')"
                >
                    Shop
                </Link>

                <template v-if="user">
                    <Link
                        :href="ordersIndex()"
                        :class="navClass('orders')"
                        :aria-current="current('orders')"
                    >
                        Orders
                    </Link>
                    <Link
                        :href="showWallet()"
                        :aria-current="current('wallet')"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border-[2.5px] border-outline px-3.5 text-base font-bold whitespace-nowrap transition-colors duration-200 ease-stall"
                        :class="
                            section === 'wallet'
                                ? 'bg-marigold text-on-marigold'
                                : 'bg-surface text-ink hover:bg-field'
                        "
                    >
                        Wallet
                        <span class="tabular-nums" data-test="balance">
                            {{ user.balance }}
                        </span>
                    </Link>
                </template>
                <Link
                    v-else
                    :href="login()"
                    :class="navClass('login')"
                    :aria-current="current('login')"
                >
                    Log in
                </Link>
            </nav>

            <div class="flex items-center gap-3">
                <ThemeToggle variant="button" />
                <Link
                    :href="showCart()"
                    :class="basketClass"
                    :aria-current="current('basket')"
                >
                    <Icon name="basket" :size="20" />
                    <span>
                        Basket (<span
                            class="tabular-nums"
                            data-test="cart-badge"
                            >{{ basketCount }}</span
                        >)
                    </span>
                </Link>
            </div>
        </div>
    </header>
</template>
