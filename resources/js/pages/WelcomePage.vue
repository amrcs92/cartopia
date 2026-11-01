<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';
import Chalkboard from '@/common/stall/Chalkboard.vue';
import SectionHeading from '@/common/stall/SectionHeading.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';
import DeliveryOptions from '@/modules/home/DeliveryOptions.vue';
import FaqList from '@/modules/home/FaqList.vue';
import HowItWorks from '@/modules/home/HowItWorks.vue';
import AisleTile from '@/modules/products/AisleTile.vue';
import FeaturedCarousel from '@/modules/products/FeaturedCarousel.vue';
import ProductCard from '@/modules/products/ProductCard.vue';
import { register } from '@/routes';
import { index as productsIndex, show as showProduct } from '@/routes/products';
import { show as showWallet } from '@/routes/wallet';
import type { Aisle, Product, ShippingMethod } from '@/types';

// The home page, in the [Home] mockup's order: the hero with today's prices,
// the featured carousel, the aisles, the counter, how shopping works, the
// wallet band, then delivery (#delivery) beside the questions (#faq).
const props = defineProps<{
    counter: Product[];
    featured: Product[];
    aisles: Aisle[];
    shippingMethods: ShippingMethod[];
}>();

const user = useCurrentUser();

function tintFor(index: number): 1 | 2 | 3 {
    return ((index % 3) + 1) as 1 | 2 | 3;
}

const walletPoints = [
    '$100 of credit when you create an account',
    'Shop as a guest; your basket comes with you when you log in',
    'Every order kept with the exact prices you paid',
];
</script>

<template>
    <Head title="Welcome" />

    <div class="flex flex-col gap-8 pt-2 pb-6 lg:gap-[72px] lg:pt-8 lg:pb-12">
        <section
            class="grid grid-cols-1 items-center gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-14"
        >
            <div class="flex flex-col items-start gap-6">
                <h1
                    class="font-serif text-[40px] leading-[1.02] font-normal tracking-[-1px] text-ink lg:text-[68px] lg:tracking-[-1.5px]"
                >
                    Picked, priced and ready to bag.
                </h1>
                <p
                    class="max-w-[470px] text-[17px] leading-[1.55] text-body lg:text-[19px]"
                >
                    Everyday staples from 30 cents. Pay from your Cartopia
                    wallet, and your basket waits for you between visits.
                </p>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <StallButton
                        variant="marigold"
                        size="lg"
                        as="link"
                        :href="productsIndex.url()"
                    >
                        Browse the stall
                    </StallButton>
                    <Link
                        v-if="!user"
                        :href="register()"
                        class="inline-flex min-h-11 items-center rounded-lg font-bold text-link underline underline-offset-4 hover:no-underline"
                    >
                        Sign up and get $100 credit
                    </Link>
                </div>
            </div>

            <Chalkboard title="Today's prices">
                <svg
                    viewBox="0 0 160 12"
                    aria-hidden="true"
                    class="-mt-2 mb-4 h-3 w-36 text-marigold lg:-mt-3 lg:w-44"
                >
                    <path
                        d="M2 8c12-6 24-6 36-1s24 5 36 0 24-5 36 0 24 5 46-1"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3.5"
                        stroke-linecap="round"
                    />
                </svg>

                <ul v-if="props.counter.length > 0" class="flex flex-col gap-3">
                    <li v-for="product in props.counter" :key="product.id">
                        <Link
                            :href="showProduct(product)"
                            class="flex items-center gap-3 rounded-xl underline-offset-4 hover:underline lg:gap-4"
                        >
                            <span
                                class="inline-flex size-[54px] shrink-0 items-center justify-center overflow-hidden rounded-full bg-chalk p-1.5"
                            >
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    alt=""
                                    class="max-h-full max-w-full object-contain"
                                />
                                <Icon
                                    v-else
                                    name="basket"
                                    :size="26"
                                    class="text-chalkboard"
                                />
                            </span>
                            <span
                                class="min-w-0 text-[27px] leading-[1.05] wrap-anywhere lg:text-[34px]"
                            >
                                {{ product.short_name ?? product.name }}
                            </span>
                            <span
                                aria-hidden="true"
                                class="mb-2 min-w-4 flex-1 self-end border-b-[3px] border-dotted border-chalk/40"
                            />
                            <span
                                class="shrink-0 text-[30px] leading-none text-marigold tabular-nums lg:text-[36px]"
                            >
                                {{ product.price }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <p v-else>Fresh stock is on its way.</p>

                <p
                    class="mt-5 border-t-2 border-dashed border-chalk/40 pt-3 text-[24px] lg:text-[26px]"
                >
                    {{
                        user
                            ? 'Fresh picks, every day.'
                            : 'New here? Your first $100 is on us.'
                    }}
                </p>
            </Chalkboard>
        </section>

        <FeaturedCarousel :products="props.featured" class="mt-10 lg:mt-0" />

        <section
            v-if="props.aisles.length > 0"
            aria-labelledby="aisles-title"
            class="flex flex-col gap-6"
        >
            <SectionHeading
                id="aisles-title"
                title="Shop by aisle"
                link-label="See all aisles"
                :href="productsIndex.url()"
            />
            <div
                class="grid grid-cols-[repeat(auto-fit,minmax(min(260px,100%),1fr))] gap-6"
            >
                <AisleTile
                    v-for="(aisle, index) in props.aisles"
                    :key="aisle.id"
                    :aisle="aisle"
                    :tint="tintFor(index)"
                />
            </div>
        </section>

        <section aria-labelledby="counter-title" class="flex flex-col gap-6">
            <SectionHeading
                id="counter-title"
                title="On the counter today"
                link-label="See all products"
                :href="productsIndex.url()"
            />
            <div
                v-if="props.counter.length > 0"
                class="grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fill,minmax(260px,1fr))] sm:gap-6"
            >
                <ProductCard
                    v-for="product in props.counter"
                    :key="product.id"
                    :product="product"
                />
            </div>
            <p v-else class="text-body">No products yet. Check back soon.</p>
        </section>

        <HowItWorks :shipping-methods="props.shippingMethods" />

        <section
            aria-labelledby="wallet-title"
            class="grid gap-8 rounded-[28px] border-[3px] border-outline bg-green-deep p-6 text-white sm:p-11 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-12"
        >
            <div class="flex flex-col items-start gap-5">
                <h2
                    id="wallet-title"
                    class="font-serif text-[28px] leading-[1.1] font-normal lg:text-[40px]"
                >
                    Your wallet pays at the till.
                </h2>
                <p
                    class="max-w-[520px] text-base leading-relaxed text-white/80 lg:text-[17px]"
                >
                    Top up anywhere from $5 to $500. Every purchase and top-up
                    is written to your ledger with the balance after it.
                </p>
                <StallButton
                    variant="marigold"
                    as="link"
                    :href="showWallet.url()"
                >
                    Open my wallet
                </StallButton>
            </div>
            <ul class="flex flex-col gap-4">
                <li
                    v-for="point in walletPoints"
                    :key="point"
                    class="flex items-start gap-3 text-base leading-snug font-semibold lg:text-[17px]"
                >
                    <span
                        aria-hidden="true"
                        class="mt-px inline-flex size-[22px] shrink-0 items-center justify-center rounded-full bg-marigold text-on-marigold"
                    >
                        <Icon name="check" :size="16" />
                    </span>
                    {{ point }}
                </li>
            </ul>
        </section>

        <div class="grid gap-8 md:grid-cols-2 md:gap-12">
            <DeliveryOptions :shipping-methods="props.shippingMethods" />
            <FaqList />
        </div>
    </div>
</template>
