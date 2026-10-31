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
</script>

<template>
    <Toaster />
</template>
