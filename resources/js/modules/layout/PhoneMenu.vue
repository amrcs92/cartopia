<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import type { IconName } from '@/common/icon/icons';
import Sheet from '@/common/sheet/Sheet.vue';
import Chip from '@/common/stall/Chip.vue';
import StallButton from '@/common/stall/StallButton.vue';
import ThemeToggle from '@/common/theme/ThemeToggle.vue';
import { home, login, logout, register } from '@/routes';
import { show as showCart } from '@/routes/cart';
import { index as ordersIndex } from '@/routes/orders';
import { index as productsIndex } from '@/routes/products';
import { edit as editProfile } from '@/routes/profile';
import { show as showWallet } from '@/routes/wallet';
import { useStoreChrome } from './useStoreChrome';

// The phone menu, a sheet from the left: the wallet, the main links, the
// aisles (fetched the first time it opens), the theme control and logging in
// or out. Any visit closes it.
const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const { user, basketCount } = useStoreChrome();

const aisles = computed(() => page.props.menuAisles ?? []);
const tints = ['tint-1', 'tint-2', 'tint-3'] as const;

watch(
    () => props.open,
    (open) => {
        if (open && page.props.menuAisles === undefined) {
            router.reload({ only: ['menuAisles'] });
        }
    },
);

watch(
    () => page.url,
    () => emit('close'),
);

type MenuLink = { label: string; href: string; icon: IconName };

const links = computed<MenuLink[]>(() => [
    { label: 'Home', href: home.url(), icon: 'pickup' },
    { label: 'Shop all products', href: productsIndex.url(), icon: 'search' },
    {
        label: `Basket (${basketCount.value})`,
        href: showCart.url(),
        icon: 'basket',
    },
    ...(user.value
        ? ([
              { label: 'My orders', href: ordersIndex.url(), icon: 'delivery' },
              {
                  label: 'Account settings',
                  href: editProfile.url(),
                  icon: 'filters',
              },
          ] satisfies MenuLink[])
        : []),
]);
</script>

<template>
    <Sheet
        :open="props.open"
        side="left"
        label="Cartopia"
        class="w-[min(318px,calc(100vw-2.5rem))]"
        @close="emit('close')"
    >
        <template #band>
            <div
                aria-hidden="true"
                class="h-3.5 shrink-0 border-b-[2.5px] border-outline bg-[repeating-linear-gradient(90deg,var(--green)_0_28px,var(--stripe)_28px_56px)]"
            />
        </template>

        <div class="flex flex-col gap-6">
            <div
                v-if="user"
                class="rounded-[18px] border-[2.5px] border-outline bg-green-deep p-4 text-primary-foreground"
            >
                <p class="text-sm font-bold">Wallet balance</p>
                <p
                    class="mt-1 font-serif text-[30px] leading-none tabular-nums"
                >
                    {{ user.balance }}
                </p>
                <StallButton
                    variant="marigold"
                    as="link"
                    :href="showWallet.url()"
                    class="mt-3"
                >
                    Top up
                </StallButton>
            </div>

            <nav aria-label="Menu">
                <ul class="flex flex-col">
                    <li v-for="link in links" :key="link.href">
                        <Link
                            :href="link.href"
                            class="flex min-h-12 items-center gap-3 rounded-xl px-2 text-[17px] font-bold text-ink transition-colors duration-200 ease-stall hover:bg-field"
                        >
                            <Icon :name="link.icon" :size="22" />
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <section v-if="aisles.length > 0" aria-labelledby="menu-aisles">
                <h2
                    id="menu-aisles"
                    class="mb-3 font-serif text-[19px] font-normal text-ink"
                >
                    Aisles
                </h2>
                <ul class="flex flex-wrap gap-2">
                    <li v-for="(aisle, index) in aisles" :key="aisle.id">
                        <Link
                            :href="
                                productsIndex.url({
                                    query: { aisle: [aisle.slug] },
                                })
                            "
                            class="block rounded-full"
                        >
                            <Chip :tone="tints[index % tints.length]">
                                {{ aisle.name }}
                            </Chip>
                        </Link>
                    </li>
                </ul>
            </section>

            <section aria-labelledby="menu-theme">
                <h2
                    id="menu-theme"
                    class="mb-3 font-serif text-[19px] font-normal text-ink"
                >
                    Theme
                </h2>
                <ThemeToggle variant="segmented" />
            </section>

            <a
                v-if="user?.is_admin"
                href="/admin"
                class="flex min-h-12 items-center rounded-xl px-2 text-[17px] font-bold text-ink transition-colors duration-200 ease-stall hover:bg-field"
            >
                Admin
            </a>

            <Link
                v-if="user"
                :href="logout()"
                as="button"
                data-test="logout-button"
                class="flex min-h-12 items-center rounded-xl px-2 text-left text-[17px] font-bold text-danger transition-colors duration-200 ease-stall hover:bg-field"
                @click="router.flushAll()"
            >
                Log out
            </Link>
            <div v-else class="flex flex-col gap-3">
                <StallButton variant="green" as="link" :href="login.url()">
                    Log in
                </StallButton>
                <StallButton variant="outline" as="link" :href="register.url()">
                    Sign up
                </StallButton>
            </div>
        </div>
    </Sheet>
</template>
