<script setup lang="ts">
// A marigold price tag with a punched hole on its left. `price` arrives
// already formatted ("$4.50").
const props = withDefaults(
    defineProps<{
        price: string;
        size?: 'sm' | 'lg';
    }>(),
    { size: 'sm' },
);
</script>

<template>
    <span
        class="relative inline-flex items-center bg-marigold font-serif leading-none font-normal text-on-marigold tabular-nums"
        :class="
            props.size === 'lg'
                ? 'rounded-l-[8px] rounded-r-[20px] border-[2.5px] border-outline py-2.5 pr-5 pl-9 text-[26px] lg:text-[40px]'
                : 'rounded-l-[6px] rounded-r-[14px] py-1.5 pr-3 pl-6 text-[19px] lg:text-[22px]'
        "
    >
        <span
            aria-hidden="true"
            class="absolute top-1/2 size-[7px] -translate-y-1/2 rounded-full bg-well"
            :class="props.size === 'lg' ? 'left-3.5' : 'left-2.5'"
        />
        {{ props.price }}
    </span>
</template>
