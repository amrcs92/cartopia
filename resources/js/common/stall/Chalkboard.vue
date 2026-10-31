<script setup lang="ts">
// A chalkboard in a wooden frame. Its colours are the fixed chalkboard, wood
// and chalk tokens, so it looks the same in both themes. The slot is set in
// Caveat chalk; callers colour prices with `text-marigold`.
const props = defineProps<{
    title?: string;
}>();
</script>

<template>
    <div
        class="rounded-[18px] border-[2.5px] border-outline bg-wood p-3 shadow-panel"
    >
        <div
            class="rounded-[8px] bg-chalkboard bg-[radial-gradient(circle_at_18%_12%,color-mix(in_srgb,var(--chalk)_8%,transparent),transparent_45%),radial-gradient(circle_at_85%_88%,color-mix(in_srgb,var(--chalk)_6%,transparent),transparent_50%)] px-6 py-5 font-chalk text-[27px] leading-tight font-bold text-chalk lg:px-8 lg:py-7 lg:text-[30px]"
        >
            <h2
                v-if="props.title"
                class="mb-3 text-[34px] leading-none lg:mb-4 lg:text-[50px]"
            >
                {{ props.title }}
            </h2>
            <slot />
        </div>
    </div>
</template>
