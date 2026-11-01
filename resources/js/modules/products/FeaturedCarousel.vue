<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, useId, useTemplateRef, watch } from 'vue';
import IconButton from '@/common/stall/IconButton.vue';
import FeaturedCard from '@/modules/products/FeaturedCard.vue';
import type { Product } from '@/types';

// "Featured products": a cream panel under a hanging wooden sign. It shows
// 3 cards on desktop and 2 on tablets; the arrows and dots glide the row one
// card at a time (500ms, ease-stall) and wrap at the ends. Cards out of view
// are hidden from the keyboard and screen readers, and a live region announces
// the position. When every product fits, the controls are left out and the
// cards keep their normal width. Below 640px it is a swipeable scroll-snap row
// with the next card peeking in. With no products it renders nothing.
const props = defineProps<{
    products: Product[];
}>();

const GAP = 24;

const isPhone = useMediaQuery('(max-width: 639.98px)');
const isDesktop = useMediaQuery('(min-width: 980px)');

const perView = computed(() => (isPhone.value ? 1 : isDesktop.value ? 3 : 2));
const total = computed(() => props.products.length);
const positions = computed(() => Math.max(1, total.value - perView.value + 1));
const slides = computed(() => !isPhone.value && total.value > perView.value);

const start = ref(0);

watch(positions, (count) => {
    start.value = Math.min(start.value, count - 1);
});

function go(to: number): void {
    const count = positions.value;
    start.value = ((to % count) + count) % count;
}

function inView(index: number): boolean {
    return (
        isPhone.value ||
        (index >= start.value && index < start.value + perView.value)
    );
}

const announcement = computed(() => {
    const from = start.value + 1;
    const to = Math.min(start.value + perView.value, total.value);

    return from === to
        ? `Showing product ${from} of ${total.value}`
        : `Showing products ${from} to ${to} of ${total.value}`;
});

const trackStyle = computed(() =>
    isPhone.value
        ? undefined
        : {
              transform: `translateX(calc(${-start.value} * (100% + ${GAP}px) / ${perView.value}))`,
          },
);

const slideStyle = computed(() =>
    isPhone.value
        ? undefined
        : {
              flexBasis: `calc((100% - ${(perView.value - 1) * GAP}px) / ${perView.value})`,
          },
);

// On phones the row scrolls natively; the position follows the scroll.
const track = useTemplateRef<HTMLElement>('track');
let frame = 0;

function onScroll(): void {
    if (!isPhone.value || frame !== 0) {
        return;
    }

    frame = requestAnimationFrame(() => {
        frame = 0;
        const row = track.value;
        const first = row?.firstElementChild as HTMLElement | null;

        if (!row || !first) {
            return;
        }

        const stride =
            first.offsetWidth +
            parseFloat(getComputedStyle(row).columnGap || '0');
        start.value = Math.min(
            total.value - 1,
            Math.max(0, Math.round(row.scrollLeft / stride)),
        );
    });
}

const titleId = useId();

function tintFor(index: number): 1 | 2 | 3 {
    return ((index % 3) + 1) as 1 | 2 | 3;
}
</script>

<template>
    <section
        v-if="total > 0"
        :aria-labelledby="titleId"
        aria-roledescription="carousel"
        class="relative rounded-[30px] border-[2.5px] border-outline bg-cream px-4 pt-14 pb-5 shadow-panel sm:px-[26px] sm:pt-[60px] sm:pb-[22px]"
    >
        <p class="sr-only" aria-live="polite" aria-atomic="true">
            {{ announcement }}
        </p>

        <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2">
            <div class="relative -rotate-2">
                <span
                    aria-hidden="true"
                    class="absolute bottom-full left-[calc(50%-61.5px)] h-7 w-[3px] rounded-t-full bg-outline"
                />
                <span
                    aria-hidden="true"
                    class="absolute bottom-full left-[calc(50%+58.5px)] h-7 w-[3px] rounded-t-full bg-outline"
                />
                <h2
                    :id="titleId"
                    class="rounded-xl border-[2.5px] border-outline bg-wood px-6 py-1 font-chalk text-[30px] leading-tight font-bold whitespace-nowrap text-chalk shadow-press sm:px-8 sm:text-[38px]"
                >
                    Featured products
                </h2>
            </div>
        </div>

        <div :class="isPhone ? '-mx-4' : '-m-2 overflow-hidden p-2'">
            <div
                ref="track"
                class="flex"
                :class="
                    isPhone
                        ? 'snap-x snap-mandatory scroll-px-4 [scrollbar-width:none] gap-4 overflow-x-auto px-4 py-2 [&::-webkit-scrollbar]:hidden'
                        : 'gap-6 transition-transform duration-500 ease-stall'
                "
                :style="trackStyle"
                @scroll.passive="onScroll"
            >
                <div
                    v-for="(product, index) in props.products"
                    :key="product.id"
                    role="group"
                    aria-roledescription="slide"
                    :aria-label="`${index + 1} of ${total}`"
                    :aria-hidden="inView(index) ? undefined : 'true'"
                    :tabindex="inView(index) ? undefined : -1"
                    :inert="!inView(index)"
                    class="relative shrink-0"
                    :class="isPhone && 'basis-[85%] snap-start'"
                    :style="slideStyle"
                >
                    <FeaturedCard :product="product" :tint="tintFor(index)" />
                </div>
            </div>
        </div>

        <div v-if="slides" class="mt-5 flex items-center justify-center gap-2">
            <IconButton
                label="Previous featured products"
                icon="chevron-left"
                @click="go(start - 1)"
            />
            <div class="flex">
                <button
                    v-for="position in positions"
                    :key="position"
                    type="button"
                    class="group inline-flex size-11 items-center justify-center rounded-full"
                    :aria-label="`Show products ${position} to ${position + perView - 1}`"
                    :aria-current="position - 1 === start ? 'true' : undefined"
                    @click="go(position - 1)"
                >
                    <span
                        aria-hidden="true"
                        class="h-3 rounded-full transition-[width,background-color] duration-200 ease-stall"
                        :class="
                            position - 1 === start
                                ? 'w-[30px] bg-green dark:bg-marigold'
                                : 'w-3 bg-outline/30 group-hover:bg-outline/60'
                        "
                    />
                </button>
            </div>
            <IconButton
                label="Next featured products"
                icon="chevron-right"
                variant="green"
                class="dark:bg-marigold dark:text-on-marigold dark:hover:bg-marigold/90"
                @click="go(start + 1)"
            />
        </div>
    </section>
</template>
