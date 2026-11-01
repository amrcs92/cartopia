<script setup lang="ts">
import { computed, ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import ProductLightbox from '@/modules/products/ProductLightbox.vue';

// The product's photo panel. With several photos it adds arrows and a
// "1 / 4" counter on the photo and a row of thumbnails under it; a swipe on
// a touch screen moves between photos too. Clicking (or tapping) the photo
// opens it in the lightbox. On a wide screen with a mouse, hovering the photo
// marks the area under the pointer and reports it through `zoom`, which the
// page shows enlarged in ProductZoomPanel over the details column.
const props = defineProps<{
    images: string[];
    name: string;
}>();

const index = defineModel<number>('index', { default: 0 });
const zoom = defineModel<{ x: number; y: number } | null>('zoom', {
    default: null,
});

const open = ref(false);
const count = computed(() => props.images.length);
const current = computed(() => props.images[index.value] ?? null);

// The marked square is 38% of the photo, so its centre stays 19% from each
// edge and the enlarged view never runs off the photo.
const clamp = (value: number) => Math.min(81, Math.max(19, value));

function step(by: number): void {
    index.value = (index.value + by + count.value) % count.value;
}

function track(event: PointerEvent): void {
    if (
        event.pointerType !== 'mouse' ||
        !window.matchMedia('(min-width: 1024px)').matches
    ) {
        return;
    }

    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
    zoom.value = {
        x: clamp(((event.clientX - box.left) / box.width) * 100),
        y: clamp(((event.clientY - box.top) / box.height) * 100),
    };
}

let swipeStart: number | null = null;

function swipeDown(event: PointerEvent): void {
    swipeStart = event.pointerType === 'mouse' ? null : event.clientX;
}

function swipeUp(event: PointerEvent): void {
    if (swipeStart === null || count.value < 2) {
        return;
    }

    const distance = event.clientX - swipeStart;
    swipeStart = null;

    if (Math.abs(distance) > 40) {
        step(distance < 0 ? 1 : -1);
    }
}

// The round arrow buttons sit on the photo's left and right edges.
const arrow =
    'absolute top-1/2 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full border-[2.5px] border-outline bg-surface text-ink shadow-press transition-colors duration-200 ease-stall hover:bg-field';

function openLightbox(): void {
    zoom.value = null;
    open.value = true;
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <div
            class="relative aspect-square touch-pan-y overflow-hidden rounded-[28px] border-[2.5px] border-outline bg-well shadow-panel"
            data-test="product-photo"
            @pointermove="track"
            @pointerleave="zoom = null"
            @pointerdown="swipeDown"
            @pointerup="swipeUp"
        >
            <button
                v-if="current"
                type="button"
                class="flex size-full cursor-zoom-in items-center justify-center p-6 sm:p-10"
                :aria-label="`Open photo ${index + 1} of ${count} in a larger view`"
                @click="openLightbox"
            >
                <img
                    :src="current"
                    :alt="props.name"
                    class="max-h-full max-w-full object-contain mix-blend-multiply"
                    draggable="false"
                />
            </button>
            <div v-else class="flex size-full items-center justify-center">
                <Icon
                    name="basket"
                    :size="96"
                    class="text-on-marigold opacity-30"
                />
            </div>

            <div
                v-if="zoom"
                aria-hidden="true"
                class="pointer-events-none absolute size-[38%] -translate-x-1/2 -translate-y-1/2 rounded-xl border-[2.5px] border-outline bg-marigold/20"
                :style="{ left: `${zoom.x}%`, top: `${zoom.y}%` }"
                data-test="zoom-area"
            />

            <template v-if="count > 1">
                <button
                    type="button"
                    aria-label="Previous photo"
                    :class="[arrow, 'left-3 sm:left-4']"
                    @click="step(-1)"
                >
                    <Icon name="chevron-left" :size="22" />
                </button>
                <button
                    type="button"
                    aria-label="Next photo"
                    :class="[arrow, 'right-3 sm:right-4']"
                    @click="step(1)"
                >
                    <Icon name="chevron-right" :size="22" />
                </button>
                <span
                    class="pointer-events-none absolute bottom-3 left-3 rounded-full border-2 border-outline bg-surface px-3 py-1 text-sm font-extrabold text-ink tabular-nums sm:bottom-4 sm:left-4"
                    data-test="photo-counter"
                >
                    {{ index + 1 }} / {{ count }}
                </span>
            </template>

            <span
                v-if="current && !zoom"
                aria-hidden="true"
                class="pointer-events-none absolute right-3 bottom-3 flex items-center gap-1.5 rounded-full border-2 border-outline bg-surface py-1 pr-3 pl-2 text-[13px] font-bold text-ink sm:right-4 sm:bottom-4 sm:text-sm"
            >
                <Icon name="zoom-in" :size="18" />
                <span class="lg:pointer-fine:hidden">Tap to enlarge</span>
                <span class="hidden lg:pointer-fine:inline">
                    Hover to zoom · Click to enlarge
                </span>
            </span>
        </div>

        <div
            v-if="count > 1"
            role="group"
            :aria-label="`${props.name} photos`"
            class="flex flex-wrap gap-3"
        >
            <button
                v-for="(image, i) in props.images"
                :key="image"
                type="button"
                class="size-16 overflow-hidden rounded-[14px] bg-well p-1.5 transition-[box-shadow,transform,opacity] duration-200 ease-stall sm:size-[76px]"
                :class="
                    i === index
                        ? '-translate-x-0.5 -translate-y-0.5 border-[2.5px] border-outline shadow-card'
                        : 'border-[1.5px] border-line-strong opacity-75 hover:border-outline hover:opacity-100'
                "
                :aria-label="`Show photo ${i + 1} of ${count}`"
                :aria-pressed="i === index"
                @click="index = i"
            >
                <img
                    :src="image"
                    alt=""
                    class="size-full object-contain mix-blend-multiply"
                />
            </button>
        </div>

        <ProductLightbox
            v-model:open="open"
            v-model:index="index"
            :images="props.images"
            :name="props.name"
        />
    </div>
</template>
