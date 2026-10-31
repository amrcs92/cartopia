<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="inline-flex min-h-11 items-center font-bold text-link underline decoration-line-strong underline-offset-4 transition-colors duration-200 ease-stall hover:decoration-current!"
    >
        <slot />
    </Link>
</template>
