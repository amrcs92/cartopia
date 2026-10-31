<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Chalkboard from '@/common/stall/Chalkboard.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { home } from '@/routes';
import { index as productsIndex } from '@/routes/products';
</script>

<template>
    <Head title="Page not found" />

    <div
        class="mx-auto flex max-w-2xl flex-col items-center gap-8 py-8 text-center lg:py-14"
    >
        <div class="w-full -rotate-2 motion-reduce:rotate-0">
            <Chalkboard>
                <p class="text-[22px] tracking-widest text-chalk/80 uppercase">
                    Closed
                </p>
                <p class="mt-2 text-[32px] leading-tight lg:text-[42px]">
                    This stall has packed up for the day
                </p>
            </Chalkboard>
        </div>

        <div class="space-y-3">
            <h1
                class="font-serif text-[32px] leading-tight text-ink lg:text-5xl"
            >
                We can't find that page
            </h1>
            <p class="text-base text-body">
                The link may be old, or the address mistyped.
            </p>
        </div>

        <div class="flex flex-wrap justify-center gap-4">
            <StallButton
                variant="marigold"
                size="lg"
                as="link"
                :href="home.url()"
            >
                Go to the home page
            </StallButton>
            <StallButton
                variant="outline"
                size="lg"
                as="link"
                :href="productsIndex.url()"
            >
                Browse the shop
            </StallButton>
        </div>
    </div>
</template>
