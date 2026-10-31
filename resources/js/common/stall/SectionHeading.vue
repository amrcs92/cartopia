<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/common/icon/Icon.vue';

// A section title (Heading 2) with an optional "see all" style link.
const props = defineProps<{
    title: string;
    linkLabel?: string;
    href?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <h2
            class="font-serif text-[28px] leading-[1.1] font-normal text-ink lg:text-[44px]"
        >
            {{ props.title }}
        </h2>
        <Link
            v-if="props.linkLabel && props.href"
            :href="props.href"
            class="inline-flex min-h-11 items-center gap-1 rounded-lg font-bold text-link underline-offset-4 hover:underline"
        >
            {{ props.linkLabel }}
            <Icon name="chevron-right" :size="18" />
        </Link>
    </div>
</template>
