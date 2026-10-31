<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/common/button/Button.vue';
import type { PaginationLink } from '@/types';

const props = defineProps<{
    links: PaginationLink[];
}>();

// Laravel labels the first and last links "&laquo; Previous" / "Next &raquo;".
const items = computed(() =>
    props.links.map((link, index) => ({
        ...link,
        label:
            index === 0
                ? 'Previous'
                : index === props.links.length - 1
                  ? 'Next'
                  : link.label,
    })),
);
</script>

<template>
    <nav
        v-if="props.links.length > 3"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-center gap-1"
    >
        <template v-for="(item, index) in items" :key="index">
            <Button
                v-if="item.url"
                :variant="item.active ? 'default' : 'outline'"
                size="sm"
                as-child
            >
                <Link
                    :href="item.url"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </Button>
            <Button
                v-else
                variant="outline"
                size="sm"
                class="pointer-events-none opacity-50"
                aria-disabled="true"
            >
                {{ item.label }}
            </Button>
        </template>
    </nav>
</template>
