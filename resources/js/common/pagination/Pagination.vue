<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Button from '@/common/button/Button.vue';
import Icon from '@/common/icon/Icon.vue';
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
        edge:
            index === 0
                ? ('previous' as const)
                : index === props.links.length - 1
                  ? ('next' as const)
                  : null,
    })),
);
</script>

<template>
    <nav
        v-if="props.links.length > 3"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-center gap-2 tabular-nums"
    >
        <template v-for="(item, index) in items" :key="index">
            <Button
                v-if="item.url"
                :variant="item.active ? 'default' : 'outline'"
                size="sm"
                class="min-w-11"
                as-child
            >
                <Link
                    :href="item.url"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    <Icon
                        v-if="item.edge === 'previous'"
                        name="chevron-left"
                        :size="18"
                    />
                    {{ item.label }}
                    <Icon
                        v-if="item.edge === 'next'"
                        name="chevron-right"
                        :size="18"
                    />
                </Link>
            </Button>
            <Button
                v-else
                variant="outline"
                size="sm"
                class="pointer-events-none min-w-11 border-line-strong opacity-50"
                aria-disabled="true"
            >
                {{ item.label }}
            </Button>
        </template>
    </nav>
</template>
