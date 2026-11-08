<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside, watchDebounced } from '@vueuse/core';
import { computed, ref, useId, useTemplateRef, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import { index as productsIndex } from '@/routes/products';
import { suggestions as searchSuggestions } from '@/routes/search';

// The header search: a sticker-outlined field that sends `q` to the shop. It
// shows the current search while you're on a results page. From two letters
// a dropdown lists up to six matching products as you type (250ms after the
// last key), without leaving the page; the last row opens every result.
// Arrow keys move through the rows, Enter opens one, Escape closes the list.
type Suggestion = {
    id: number;
    name: string;
    price: string;
    image_url: string | null;
    url: string;
};

const MIN_LENGTH = 2;

const page = usePage();

function currentQuery(): string {
    const query = page.url.split('?')[1] ?? '';

    return new URLSearchParams(query).get('q') ?? '';
}

const search = ref(currentQuery());
const results = ref<Suggestion[]>([]);
const total = ref(0);
const answeredFor = ref('');
const open = ref(false);
// The highlighted row: -1 is the field itself, then each product, then
// "See all results".
const active = ref(-1);

const listId = useId();
const root = useTemplateRef<HTMLElement>('root');

const term = computed(() => search.value.trim());
const ready = computed(
    () => term.value.length >= MIN_LENGTH && answeredFor.value === term.value,
);
const rowCount = computed(() =>
    results.value.length > 0 ? results.value.length + 1 : 0,
);
const showList = computed(() => open.value && ready.value);

watch(
    () => page.url,
    () => {
        search.value = currentQuery();
        close();
    },
);

let controller: AbortController | null = null;

watchDebounced(
    term,
    async (value) => {
        controller?.abort();

        if (value.length < MIN_LENGTH) {
            results.value = [];
            answeredFor.value = '';

            return;
        }

        controller = new AbortController();

        try {
            const response = await fetch(
                searchSuggestions.url({ query: { q: value } }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );

            if (!response.ok) {
                return;
            }

            const body = (await response.json()) as {
                query: string;
                total: number;
                products: Suggestion[];
            };

            // Only the answer for what's in the box now; an older one that
            // arrives late is dropped.
            if (body.query === term.value) {
                results.value = body.products;
                total.value = body.total;
                answeredFor.value = body.query;
                active.value = -1;
            }
        } catch (error) {
            if ((error as Error).name !== 'AbortError') {
                throw error;
            }
        }
    },
    { debounce: 250 },
);

onClickOutside(root, close);

function close(): void {
    open.value = false;
    active.value = -1;
}

function submit(): void {
    close();
    router.get(productsIndex.url(), term.value === '' ? {} : { q: term.value });
}

function choose(index: number): void {
    const product = results.value[index];

    if (product === undefined) {
        submit();

        return;
    }

    close();
    router.visit(product.url);
}

// Steps through the field, the rows and back round to the field.
function move(by: number): void {
    if (!ready.value || rowCount.value === 0) {
        return;
    }

    const positions = rowCount.value + 1;

    open.value = true;
    active.value = ((active.value + 1 + by + positions) % positions) - 1;
}

function onEnter(): void {
    if (showList.value && active.value >= 0) {
        choose(active.value);

        return;
    }

    submit();
}

// The highlighted row: the field colour with a marigold bar on its left.
const highlighted = 'bg-field shadow-[inset_4px_0_0_var(--marigold)]';

function optionId(index: number): string {
    return `${listId}-${index}`;
}

const status = computed(() => {
    if (!showList.value) {
        return '';
    }

    return total.value === 0
        ? `No products match ${term.value}`
        : `${total.value} ${total.value === 1 ? 'product matches' : 'products match'}. Use the arrow keys to choose.`;
});
</script>

<template>
    <form
        ref="root"
        role="search"
        :action="productsIndex.url()"
        method="get"
        class="relative"
        @submit.prevent="onEnter"
    >
        <label
            class="flex h-12 items-center gap-2.5 rounded-[14px] border-[2.5px] border-outline bg-surface px-3.5 text-subtle has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marigold has-[:focus-visible]:outline-solid"
        >
            <Icon name="search" :size="20" />
            <span class="sr-only">Search products</span>
            <input
                v-model="search"
                type="search"
                name="q"
                placeholder="Search products"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="showList"
                :aria-controls="listId"
                :aria-activedescendant="
                    showList && active >= 0 ? optionId(active) : undefined
                "
                class="h-full min-w-0 flex-1 bg-transparent text-base text-ink placeholder:text-subtle focus-visible:outline-none"
                @input="open = true"
                @focus="open = true"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.esc="close"
            />
        </label>

        <div
            v-show="showList"
            data-test="search-suggestions"
            class="absolute inset-x-0 top-full z-40 mt-2 overflow-hidden rounded-[18px] border-[2.5px] border-outline bg-surface shadow-card"
        >
            <ul
                v-if="results.length > 0"
                :id="listId"
                role="listbox"
                aria-label="Matching products"
                class="py-1.5"
            >
                <li
                    v-for="(product, index) in results"
                    :id="optionId(index)"
                    :key="product.id"
                    role="option"
                    :aria-selected="active === index"
                    class="flex cursor-pointer items-center gap-3 px-3 py-2"
                    :class="active === index && highlighted"
                    @mousedown.prevent
                    @mouseenter="active = index"
                    @click="choose(index)"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-[10px] bg-well p-1"
                    >
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            alt=""
                            class="max-h-full max-w-full object-contain mix-blend-multiply"
                        />
                        <Icon
                            v-else
                            name="basket"
                            :size="20"
                            class="text-on-marigold opacity-30"
                        />
                    </span>
                    <span class="min-w-0 flex-1 truncate font-bold text-ink">{{
                        product.name
                    }}</span>
                    <span class="shrink-0 text-sm font-bold text-body">{{
                        product.price
                    }}</span>
                </li>
                <li
                    :id="optionId(results.length)"
                    role="option"
                    :aria-selected="active === results.length"
                    class="mt-1.5 flex cursor-pointer items-center gap-1.5 border-t-[1.5px] border-line px-3 pt-3 pb-2 text-sm font-bold text-link"
                    :class="active === results.length && highlighted"
                    @mousedown.prevent
                    @mouseenter="active = results.length"
                    @click="choose(results.length)"
                >
                    {{
                        total === 1
                            ? `See 1 result for “${term}”`
                            : `See all ${total} results for “${term}”`
                    }}
                    <Icon name="chevron-right" :size="16" />
                </li>
            </ul>
            <p v-else class="px-4 py-3 text-sm text-body">
                No products match “{{ term }}”
            </p>
        </div>

        <p class="sr-only" aria-live="polite">{{ status }}</p>
    </form>
</template>
