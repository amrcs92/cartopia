<script setup lang="ts">
import AppContent from '@/common/app/AppContent.vue';
import AppHeader from '@/common/app/AppHeader.vue';
import AppShell from '@/common/app/AppShell.vue';
import { Toaster } from '@/lib/shadcn/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent variant="header">
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
