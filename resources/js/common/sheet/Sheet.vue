<script setup lang="ts">
import { DialogContent, DialogPortal } from 'reka-ui';
import { watch } from 'vue';
import IconButton from '@/common/stall/IconButton.vue';
import { Sheet, SheetTitle } from '@/lib/shadcn/sheet';
import SheetOverlay from '@/lib/shadcn/sheet/SheetOverlay.vue';

// A modal panel that slides in from the right or left, or rises from the
// bottom, over a dimmed page. The dialog primitives trap focus and close it on
// Escape or a scrim click (each emits `close`); focus then returns to whatever
// was focused when it opened. `label` is the visible title and its name.
const props = defineProps<{
    open: boolean;
    side: 'right' | 'left' | 'bottom';
    label: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

let opener: HTMLElement | null = null;

watch(
    () => props.open,
    (open) => {
        if (open && typeof document !== 'undefined') {
            opener =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
        }
    },
    { immediate: true },
);

function onOpenChange(open: boolean): void {
    if (!open) {
        emit('close');
    }
}

function restoreFocus(event: Event): void {
    event.preventDefault();

    if (opener?.isConnected) {
        opener.focus();
    }

    opener = null;
}

const sides = {
    right: 'inset-y-0 right-0 h-dvh w-[min(26rem,calc(100vw-2.5rem))] rounded-l-[24px] border-l-[2.5px] data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
    left: 'inset-y-0 left-0 h-dvh w-[min(26rem,calc(100vw-2.5rem))] rounded-r-[24px] border-r-[2.5px] data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left',
    bottom: 'inset-x-0 bottom-0 max-h-[85dvh] rounded-t-[24px] border-t-[2.5px] data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
} as const;
</script>

<template>
    <Sheet :open="props.open" @update:open="onOpenChange">
        <DialogPortal>
            <SheetOverlay class="bg-chalkboard/60 duration-300 ease-stall" />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed z-50 flex flex-col border-outline bg-surface text-ink outline-none data-[state=closed]:animate-out data-[state=open]:animate-in"
                :class="[sides[props.side], 'duration-300 ease-stall']"
                @close-auto-focus="restoreFocus"
            >
                <div
                    class="flex items-center justify-between gap-4 border-b-[1.5px] border-line px-5 py-3"
                >
                    <SheetTitle
                        class="font-serif text-[22px] leading-tight font-normal text-ink"
                    >
                        {{ props.label }}
                    </SheetTitle>
                    <IconButton
                        label="Close"
                        icon="close"
                        @click="emit('close')"
                    />
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-5">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="border-t-[1.5px] border-line px-5 py-4"
                >
                    <slot name="footer" />
                </div>
            </DialogContent>
        </DialogPortal>
    </Sheet>
</template>
