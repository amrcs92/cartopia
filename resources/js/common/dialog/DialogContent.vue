<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { DialogContent } from '@/lib/shadcn/dialog';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

// The primitive's close button carries data-slot="dialog-close"; these give it
// a 44px target and the stall colours.
const close =
    '[&>[data-slot=dialog-close]]:top-3 [&>[data-slot=dialog-close]]:right-3 [&>[data-slot=dialog-close]]:inline-flex [&>[data-slot=dialog-close]]:size-11 [&>[data-slot=dialog-close]]:items-center [&>[data-slot=dialog-close]]:justify-center [&>[data-slot=dialog-close]]:rounded-xl [&>[data-slot=dialog-close]]:text-ink [&>[data-slot=dialog-close]]:opacity-100 [&>[data-slot=dialog-close]]:hover:bg-field';
</script>

<template>
    <DialogContent
        :class="
            cn(
                'gap-4 rounded-[24px] border-[2.5px] border-outline bg-surface p-6 text-ink shadow-panel sm:p-8',
                close,
                props.class,
            )
        "
    >
        <slot />
    </DialogContent>
</template>
