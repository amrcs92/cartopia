<script setup lang="ts">
import { useId } from 'vue';
import Icon from '@/common/icon/Icon.vue';

// A round chip. `selected` adds a check mark as well as its colour. When
// `removable`, a remove button (named "Remove <chip text>", with a 44px hit
// area) emits `remove`. A chip never grows wider than its container; long
// text is cut off with an ellipsis (its accessible name keeps all of it).
const props = withDefaults(
    defineProps<{
        tone?: 'tint-1' | 'tint-2' | 'tint-3' | 'selected' | 'plain';
        removable?: boolean;
    }>(),
    { tone: 'plain', removable: false },
);

const emit = defineEmits<{
    remove: [];
}>();

const toneClasses: Record<NonNullable<typeof props.tone>, string> = {
    'tint-1': 'border-line-strong bg-tint-1',
    'tint-2': 'border-line-strong bg-tint-2',
    'tint-3': 'border-line-strong bg-tint-3',
    selected: 'border-outline bg-selected',
    plain: 'border-line-strong bg-surface',
};

const id = useId();
</script>

<template>
    <span
        class="inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border-2 px-3.5 text-[14px] font-semibold whitespace-nowrap text-ink lg:text-[15px]"
        :class="[toneClasses[props.tone], props.removable && 'pr-1.5']"
    >
        <Icon v-if="props.tone === 'selected'" name="check" :size="16" />
        <span :id="`${id}-text`" class="min-w-0 truncate"><slot /></span>
        <button
            v-if="props.removable"
            type="button"
            :aria-labelledby="`${id}-remove ${id}-text`"
            class="relative inline-flex size-6 items-center justify-center rounded-full text-ink transition-colors duration-200 ease-stall after:absolute after:-inset-2.5 after:content-[''] hover:bg-field"
            @click="emit('remove')"
        >
            <span :id="`${id}-remove`" class="sr-only">Remove</span>
            <Icon name="close" :size="14" />
        </button>
    </span>
</template>
