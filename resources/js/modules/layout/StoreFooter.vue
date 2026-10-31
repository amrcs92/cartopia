<script setup lang="ts">
import { Link, router, usePage, WhenVisible } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/common/brand/AppLogo.vue';
import { home, login, logout } from '@/routes';
import { show as showCart } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { edit as editProfile } from '@/routes/profile';
import { show as showWallet } from '@/routes/wallet';
import { useStoreChrome } from './useStoreChrome';

// The footer: the logo and line, then Shop (with the aisles, fetched once the
// footer scrolls into view), Your account and Help, and the copyright.
const page = usePage();
const { user } = useStoreChrome();

const aisles = computed(() => (page.props.menuAisles ?? []).slice(0, 6));
const year = new Date().getFullYear();

const linkClass =
    'rounded-md font-semibold text-chalk/85 underline-offset-4 transition-colors duration-200 ease-stall hover:text-marigold hover:underline';
const headingClass = 'mb-3 font-serif text-[19px] font-normal text-chalk';
</script>

<template>
    <footer class="bg-footer text-chalk/85">
        <div
            aria-hidden="true"
            class="h-3.5 border-t-[2.5px] border-outline bg-[repeating-linear-gradient(90deg,var(--marigold)_0_28px,var(--green)_28px_56px)]"
        />
        <div
            class="mx-auto grid max-w-[1200px] gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-[1.5fr_1fr_1fr_1fr] lg:py-12"
        >
            <div class="max-w-xs">
                <Link
                    :href="home()"
                    aria-label="Cartopia home"
                    class="inline-block rounded-xl text-chalk"
                >
                    <AppLogo />
                </Link>
                <p class="mt-4 text-[15px] leading-relaxed">
                    A small online market for everyday staples, paid from your
                    own Cartopia wallet.
                </p>
            </div>

            <nav aria-labelledby="footer-shop">
                <h2 id="footer-shop" :class="headingClass">Shop</h2>
                <ul class="flex flex-col gap-2 text-[15px]">
                    <li>
                        <Link :href="productsIndex()" :class="linkClass">
                            All products
                        </Link>
                    </li>
                    <li v-for="aisle in aisles" :key="aisle.id">
                        <Link
                            :href="
                                productsIndex.url({
                                    query: { aisle: [aisle.slug] },
                                })
                            "
                            :class="linkClass"
                        >
                            {{ aisle.name }}
                        </Link>
                    </li>
                </ul>
                <!-- An empty marker: once it nears the viewport, the aisles load. -->
                <WhenVisible data="menuAisles" :buffer="200" />
            </nav>

            <nav aria-labelledby="footer-account">
                <h2 id="footer-account" :class="headingClass">Your account</h2>
                <ul class="flex flex-col gap-2 text-[15px]">
                    <li>
                        <Link :href="ordersIndex()" :class="linkClass">
                            Orders
                        </Link>
                    </li>
                    <li>
                        <Link :href="showWallet()" :class="linkClass">
                            Wallet
                        </Link>
                    </li>
                    <li>
                        <Link :href="showCart()" :class="linkClass">
                            Basket
                        </Link>
                    </li>
                    <template v-if="user">
                        <li>
                            <Link :href="editProfile()" :class="linkClass">
                                Account settings
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="logout()"
                                as="button"
                                :class="linkClass"
                                @click="router.flushAll()"
                            >
                                Log out
                            </Link>
                        </li>
                    </template>
                    <li v-else>
                        <Link :href="login()" :class="linkClass">Log in</Link>
                    </li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-help">
                <h2 id="footer-help" :class="headingClass">Help</h2>
                <ul class="flex flex-col gap-2 text-[15px]">
                    <li>
                        <Link
                            :href="`${home.url()}#delivery`"
                            :class="linkClass"
                        >
                            Delivery options
                        </Link>
                    </li>
                    <li>
                        <Link :href="`${home.url()}#faq`" :class="linkClass">
                            Questions
                        </Link>
                    </li>
                    <li>
                        <Link :href="showWallet()" :class="linkClass">
                            Topping up
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
        <div class="border-t-[1.5px] border-chalk/15">
            <p
                class="mx-auto max-w-[1200px] px-4 py-5 text-sm font-semibold sm:px-6"
            >
                &copy; {{ year }} Cartopia
            </p>
        </div>
    </footer>
</template>
