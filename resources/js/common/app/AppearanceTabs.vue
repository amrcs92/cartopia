<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'Device' },
] as const;
</script>

<template>
    <div
        role="group"
        aria-label="Colour theme"
        class="grid w-full grid-cols-3 gap-1 rounded-[14px] border-2 border-outline bg-field p-1 sm:inline-grid sm:w-auto"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            :class="[
                'flex min-h-11 items-center justify-center gap-2 rounded-[10px] px-3 text-base font-bold text-ink transition-colors duration-200 ease-stall focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-marigold focus-visible:outline-solid sm:px-4',
                appearance === value
                    ? 'bg-marigold text-on-marigold'
                    : 'hover:bg-line',
            ]"
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="size-4" />
            {{ label }}
        </button>
    </div>
</template>
