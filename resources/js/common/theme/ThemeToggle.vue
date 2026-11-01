<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import IconButton from '@/common/stall/IconButton.vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import type { Appearance } from '@/composables/useAppearance';

// `button` flips between light and dark (a moon in light mode, a sun in dark
// mode); `segmented` offers Light / Dark / Device for the phone menu. On a
// flip the shown icon sinks out of the bottom of the button as the other rises
// in behind it, like the sun setting and the moon coming up. The motion only
// switches on after the first frame, so the saved theme arriving on mount
// doesn't play it.
const props = defineProps<{
    variant: 'button' | 'segmented';
}>();

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const animate = ref(false);

onMounted(() => {
    requestAnimationFrame(() => {
        animate.value = true;
    });
});

function iconClass(shown: boolean): string {
    return cn(
        animate.value &&
            'transition-[translate,opacity] duration-400 ease-stall',
        shown
            ? 'translate-y-0 opacity-100 delay-100'
            : 'translate-y-8 opacity-0',
    );
}

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
        class="overflow-hidden"
        @click="updateAppearance(isDark ? 'light' : 'dark')"
    >
        <span
            v-for="name in ['moon', 'sun'] as const"
            :key="name"
            :data-theme-icon="name"
            :data-shown="(name === 'sun') === isDark"
            class="absolute inset-0 flex items-center justify-center"
            :class="iconClass((name === 'sun') === isDark)"
        >
            <Icon :name="name" :size="22" />
        </span>
    </IconButton>
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
