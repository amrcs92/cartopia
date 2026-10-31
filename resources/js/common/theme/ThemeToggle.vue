<script setup lang="ts">
import { computed } from 'vue';
import IconButton from '@/common/stall/IconButton.vue';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';

// `button` flips between light and dark (a moon in light mode, a sun in dark
// mode); `segmented` offers Light / Dark / Device for the phone menu.
const props = defineProps<{
    variant: 'button' | 'segmented';
}>();

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'Device' },
];
</script>

<template>
    <IconButton
        v-if="props.variant === 'button'"
        :label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :icon="isDark ? 'sun' : 'moon'"
        @click="updateAppearance(isDark ? 'light' : 'dark')"
    />
    <div
        v-else
        role="group"
        aria-label="Theme"
        class="inline-flex w-full gap-1 rounded-xl border-2 border-line-strong bg-field p-1"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="appearance === option.value"
            class="min-h-11 flex-1 rounded-[10px] border-2 px-3 text-[15px] font-bold transition-colors duration-200 ease-stall"
            :class="
                appearance === option.value
                    ? 'border-outline bg-surface text-ink'
                    : 'border-transparent text-subtle hover:text-ink'
            "
            @click="updateAppearance(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
