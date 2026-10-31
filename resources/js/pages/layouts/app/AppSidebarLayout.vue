<script setup lang="ts">
import AppContent from '@/common/app/AppContent.vue';
import AppShell from '@/common/app/AppShell.vue';
import AppSidebar from '@/common/app/AppSidebar.vue';
import AppSidebarHeader from '@/common/app/AppSidebarHeader.vue';
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
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
