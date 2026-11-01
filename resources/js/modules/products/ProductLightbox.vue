<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/lib/shadcn/dialog';

// The product photos full screen on a dark backdrop: "Photo 2 of 4" and a
// Close button along the top, the photo between previous and next arrows,
// and the thumbnails under it. Clicking (or tapping) the photo zooms in on
// that spot and again zooms out; the arrow keys move between photos, a swipe
// does on a touch screen, and Esc closes it. The backdrop stays dark in both
// themes so the photo stands out.
const props = defineProps<{
    images: string[];
    name: string;
}>();

const open = defineModel<boolean>('open', { required: true });
const index = defineModel<number>('index', { required: true });

const zoomed = ref(false);
const origin = ref('50% 50%');
const count = computed(() => props.images.length);

watch([open, index], () => {
    zoomed.value = false;
});

function step(by: number): void {
    if (count.value > 1) {
        index.value = (index.value + by + count.value) % count.value;
    }
}

function toggleZoom(event: MouseEvent): void {
    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
    origin.value = `${((event.clientX - box.left) / box.width) * 100}% ${((event.clientY - box.top) / box.height) * 100}%`;
    zoomed.value = !zoomed.value;
}

let swipeStart: number | null = null;

function swipeDown(event: PointerEvent): void {
    swipeStart =
        event.pointerType === 'mouse' || zoomed.value ? null : event.clientX;
}

function swipeUp(event: PointerEvent): void {
    if (swipeStart === null) {
        return;
    }

    const distance = event.clientX - swipeStart;
    swipeStart = null;

    if (Math.abs(distance) > 40) {
        step(distance < 0 ? 1 : -1);
    }
}

const arrow =
    'hidden size-14 shrink-0 items-center justify-center rounded-full border-[2.5px] border-[#e9e3d0] bg-[#1a251f] text-[#f1eee3] transition-colors duration-200 ease-stall hover:bg-[#223029] sm:inline-flex';
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="top-0 left-0 flex h-dvh w-screen max-w-none translate-x-0 translate-y-0 flex-col gap-0 rounded-none border-0 bg-[rgba(9,14,11,0.94)] p-0 text-[#f1eee3] shadow-none sm:max-w-none"
            data-test="lightbox"
            @keydown.left.prevent="step(-1)"
            @keydown.right.prevent="step(1)"
        >
            <div class="flex items-center justify-between gap-4 p-4 sm:p-7">
                <DialogTitle
                    class="text-base font-bold text-[#c9d3cb] tabular-nums"
                >
                    <span class="sr-only">{{ props.name }}, </span>
                    Photo {{ index + 1 }} of {{ count }}
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Click the photo to zoom in. Use the arrow keys to move
                    between photos.
                </DialogDescription>
                <DialogClose
                    class="inline-flex min-h-12 items-center gap-2 rounded-[14px] border-[2.5px] border-[#e9e3d0] bg-marigold px-4 text-[17px] font-extrabold text-[#14201a] shadow-[3px_3px_0_#2e7a54] transition-[translate,box-shadow] duration-200 ease-stall hover:-translate-x-px hover:-translate-y-px hover:shadow-[4px_4px_0_#2e7a54]"
                >
                    <Icon name="close" :size="20" />
                    Close
                </DialogClose>
            </div>

            <div
                class="flex min-h-0 flex-1 flex-col items-center justify-center gap-6 px-4 pb-8 sm:px-7"
            >
                <div class="flex w-full items-center justify-center gap-6">
                    <button
                        v-if="count > 1"
                        type="button"
                        aria-label="Previous photo"
                        :class="arrow"
                        @click="step(-1)"
                    >
                        <Icon name="chevron-left" :size="24" />
                    </button>
                    <div
                        class="relative aspect-square w-[min(620px,100%,calc(100dvh-17rem))] touch-pan-y overflow-hidden rounded-[28px] border-[2.5px] border-[#e9e3d0] bg-well shadow-[8px_8px_0_#2e7a54]"
                        @pointerdown="swipeDown"
                        @pointerup="swipeUp"
                    >
                        <button
                            type="button"
                            class="flex size-full items-center justify-center p-6 sm:p-10"
                            :class="
                                zoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'
                            "
                            :aria-label="zoomed ? 'Zoom out' : 'Zoom in'"
                            @click="toggleZoom"
                        >
                            <img
                                :src="props.images[index]"
                                :alt="`${props.name}, photo ${index + 1} of ${count}`"
                                class="max-h-full max-w-full object-contain mix-blend-multiply transition-transform duration-200 ease-stall"
                                :style="{
                                    transform: zoomed ? 'scale(2)' : 'none',
                                    transformOrigin: origin,
                                }"
                                draggable="false"
                            />
                        </button>
                    </div>
                    <button
                        v-if="count > 1"
                        type="button"
                        aria-label="Next photo"
                        :class="arrow"
                        @click="step(1)"
                    >
                        <Icon name="chevron-right" :size="24" />
                    </button>
                </div>

                <div
                    v-if="count > 1"
                    role="group"
                    :aria-label="`${props.name} photos`"
                    class="flex flex-wrap justify-center gap-3"
                >
                    <button
                        v-for="(image, i) in props.images"
                        :key="image"
                        type="button"
                        class="size-16 overflow-hidden rounded-[14px] bg-well p-1.5 transition-opacity duration-200 ease-stall sm:size-[72px]"
                        :class="
                            i === index
                                ? 'border-[3px] border-marigold'
                                : 'border-2 border-[#4a6455] opacity-70 hover:opacity-100'
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
                <p class="text-[15px] font-semibold text-[#b3c0b6]">
                    <span class="pointer-fine:hidden">
                        Tap the photo to zoom in, swipe for more
                    </span>
                    <span class="hidden pointer-fine:inline">
                        Click the photo to zoom in
                    </span>
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
