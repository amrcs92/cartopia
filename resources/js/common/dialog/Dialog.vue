<script setup lang="ts">
import { Dialog } from '@/lib/shadcn/dialog';

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog v-model:open="open">
        <slot />
    </Dialog>
</template>
