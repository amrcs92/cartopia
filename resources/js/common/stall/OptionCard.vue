<script setup lang="ts">
// A selectable card that wraps a native radio supplied by the caller, so the
// whole card is the radio's label. Selection is shown by colour and by a
// thicker outline; padding offsets the border change so nothing shifts.
const props = defineProps<{
    selected: boolean;
    disabled?: boolean;
}>();
</script>

<template>
    <label
        class="flex items-start gap-3 rounded-[14px] text-ink transition-[background-color,border-color] duration-200 ease-stall has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-[3px] has-[:focus-visible]:outline-marigold has-[:focus-visible]:outline-solid"
        :class="[
            props.selected
                ? 'border-[2.5px] border-outline bg-selected p-4'
                : 'border-[1.5px] border-line-strong bg-surface p-[17px]',
            props.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            !props.selected && !props.disabled && 'hover:border-outline',
        ]"
    >
        <slot />
    </label>
</template>
