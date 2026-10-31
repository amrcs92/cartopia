<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import { index as productsIndex } from '@/routes/products';

// The header search: a sticker-outlined field that sends `q` to the shop. It
// shows the current search while you're on a results page.
const page = usePage();

function currentQuery(): string {
    const query = page.url.split('?')[1] ?? '';

    return new URLSearchParams(query).get('q') ?? '';
}

const search = ref(currentQuery());

watch(
    () => page.url,
    () => {
        search.value = currentQuery();
    },
);

function submit(): void {
    const q = search.value.trim();

    router.get(productsIndex.url(), q === '' ? {} : { q });
}
</script>

<template>
    <form
        role="search"
        :action="productsIndex.url()"
        method="get"
        @submit.prevent="submit"
    >
        <label
            class="flex h-12 items-center gap-2.5 rounded-[14px] border-[2.5px] border-outline bg-surface px-3.5 text-subtle has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marigold has-[:focus-visible]:outline-solid"
        >
            <Icon name="search" :size="20" />
            <span class="sr-only">Search the stall</span>
            <input
                v-model="search"
                type="search"
                name="q"
                placeholder="Search the stall"
                autocomplete="off"
                class="h-full min-w-0 flex-1 bg-transparent text-base text-ink placeholder:text-subtle focus-visible:outline-none"
            />
        </label>
    </form>
</template>
