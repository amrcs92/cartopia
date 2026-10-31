<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/lib/shadcn/sonner';
import type { Flash } from '@/types';

// Turns the shared `flash.success` / `flash.error` props into toasts. The
// kit's `Inertia::flash('toast', ...)` toasts (lib/flashToast.ts) render in
// this same Toaster.
const page = usePage();

function show(flash: Flash | undefined): void {
    if (flash?.success) {
        toast.success(flash.success);
    }

    if (flash?.error) {
        toast.error(flash.error);
    }
}

onMounted(() => show(page.props.flash));

watch(
    () => page.props.flash,
    (flash) => show(flash),
);

// Stall stickers: surface card, 2.5px outline, hard shadow; toasts stay 4s.
const toastOptions = {
    classes: {
        toast: 'font-sans! rounded-[14px]! border-[2.5px]! border-outline! bg-surface! text-ink! shadow-press! px-4! py-3.5! gap-3!',
        title: 'text-[15px] font-bold',
        description: 'text-body!',
        success: '[&_[data-icon]]:text-chip-ink',
        error: '[&_[data-icon]]:text-danger',
    },
};
</script>

<template>
    <Toaster :duration="4000" :toast-options="toastOptions" />
</template>
