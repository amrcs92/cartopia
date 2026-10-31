<script setup lang="ts">
import { Toaster } from '@/lib/shadcn/sonner';

// The Toaster that `Inertia::flash('toast', ...)` messages render in
// (lib/flashToast.ts shows them). Flash is one-shot, so Back/Forward never
// replays a toast.

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
