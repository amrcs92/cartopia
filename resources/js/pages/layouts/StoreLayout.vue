<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LogOut,
    Package,
    Search,
    Settings,
    ShoppingCart,
    User,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/common/app/Breadcrumbs.vue';
import Badge from '@/common/badge/Badge.vue';
import Button from '@/common/button/Button.vue';
import DropdownMenu from '@/common/dropdown-menu/DropdownMenu.vue';
import DropdownMenuItem from '@/common/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuLabel from '@/common/dropdown-menu/DropdownMenuLabel.vue';
import DropdownMenuSeparator from '@/common/dropdown-menu/DropdownMenuSeparator.vue';
import FlashToast from '@/common/flash-toast/FlashToast.vue';
import Input from '@/common/input/Input.vue';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';
import { home, login, logout, register } from '@/routes';
import { show as showCart } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { edit as editProfile } from '@/routes/profile';
import { show as showWallet } from '@/routes/wallet';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();
const user = useCurrentUser();
const cartCount = computed(() => page.props.cart.count);
const appName = computed(() => page.props.name);
const year = new Date().getFullYear();

const search = ref('');

function submitSearch(): void {
    const q = search.value.trim();

    router.get(productsIndex(), q === '' ? {} : { q });
}

function handleLogout(): void {
    router.flushAll();
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:flex-nowrap"
            >
                <Link
                    :href="home()"
                    class="flex shrink-0 items-center gap-2"
                    aria-label="Cartopia home"
                >
                    <img
                        src="/images/cartopia_logo.png"
                        alt="Cartopia"
                        class="h-9 w-auto"
                    />
                </Link>

                <form
                    role="search"
                    class="order-last w-full sm:order-none sm:flex-1"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative mx-auto max-w-xl">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="search"
                            type="search"
                            name="q"
                            placeholder="Search products"
                            aria-label="Search products"
                            class="pl-9"
                        />
                    </div>
                </form>

                <nav class="ml-auto flex items-center gap-2">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="productsIndex()">Shop</Link>
                    </Button>

                    <Link
                        :href="showCart()"
                        class="relative inline-flex size-9 items-center justify-center rounded-md hover:bg-accent"
                        :aria-label="`Cart: ${cartCount} items`"
                        data-test="cart-badge"
                    >
                        <ShoppingCart class="size-5" aria-hidden="true" />
                        <Badge
                            v-if="cartCount > 0"
                            class="absolute -top-1 -right-1 h-5 min-w-5 rounded-full px-1 tabular-nums"
                        >
                            {{ cartCount }}
                        </Badge>
                    </Link>

                    <template v-if="user">
                        <span
                            class="hidden text-sm font-medium tabular-nums sm:inline"
                            data-test="balance"
                        >
                            {{ user.balance }}
                        </span>

                        <DropdownMenu align="end" content-class="w-56">
                            <template #trigger>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Open user menu"
                                >
                                    <User class="size-5" />
                                </Button>
                            </template>

                            <DropdownMenuLabel class="font-normal">
                                <div class="truncate font-medium">
                                    {{ user.name }}
                                </div>
                                <div
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ user.email }}
                                </div>
                                <div class="mt-1 text-xs sm:hidden">
                                    Balance: {{ user.balance }}
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="ordersIndex()"
                                    class="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <Package class="size-4" />
                                    Orders
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="showWallet()"
                                    class="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <Wallet class="size-4" />
                                    Wallet
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="editProfile()"
                                    class="flex w-full cursor-pointer items-center gap-2"
                                >
                                    <Settings class="size-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="logout()"
                                    as="button"
                                    class="flex w-full cursor-pointer items-center gap-2"
                                    data-test="logout-button"
                                    @click="handleLogout"
                                >
                                    <LogOut class="size-4" />
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenu>
                    </template>

                    <template v-else>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button size="sm" as-child>
                            <Link :href="register()">Register</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
            <Breadcrumbs
                v-if="breadcrumbs.length > 0"
                :breadcrumbs="breadcrumbs"
                class="mb-4"
            />
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-muted-foreground sm:flex-row"
            >
                <p>&copy; {{ year }} {{ appName }}. All rights reserved.</p>
                <Link :href="productsIndex()" class="hover:text-foreground">
                    Browse all products
                </Link>
            </div>
        </footer>

        <FlashToast />
    </div>
</template>
