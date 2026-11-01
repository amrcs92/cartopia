<script setup lang="ts">
import { computed } from 'vue';

// The enlarged view of the area marked on the product photo, laid over the
// details column while the mouse is on the photo. The marked square is 38%
// of the photo, so the view is 1 / 0.38 ≈ 2.63 times larger, moved so the
// marked area's centre sits in the middle. It copies the photo panel's
// padding, so the two line up exactly.
const props = defineProps<{
    image: string;
    zoom: { x: number; y: number };
}>();

const layer = computed(
    () => `scale(2.63) translate(${50 - props.zoom.x}%, ${50 - props.zoom.y}%)`,
);
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-x-0 top-0 z-10 hidden aspect-square overflow-hidden rounded-[28px] border-[2.5px] border-outline bg-well shadow-panel lg:block"
        data-test="zoom-panel"
    >
        <div
            class="flex size-full items-center justify-center p-10"
            :style="{ transform: layer }"
        >
            <img
                :src="props.image"
                alt=""
                class="max-h-full max-w-full object-contain mix-blend-multiply"
            />
        </div>
    </div>
</template>
