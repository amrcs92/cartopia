<script setup lang="ts">
import Sheet from '@/common/sheet/Sheet.vue';
import StallButton from '@/common/stall/StallButton.vue';
import ToggleChip from '@/modules/products/ToggleChip.vue';
import {
    clearedFilters,
    isShortcut,
    PRICE_SHORTCUTS,
    productCount,
    RATING_STEPS,
    visitShop,
    withShortcut,
} from '@/modules/products/shopFilters';
import type { ShopFilters } from '@/modules/products/shopFilters';

// The phone's filters, in a sheet that rises from the bottom: price
// shortcuts, a minimum rating, rated and featured only. Each change visits
// the shop straight away (the sheet stays open), so "Show N products" always
// reads the current total; pressing it just closes the sheet. Search lives in
// the phone header, and the aisle row and sort sit on the page itself.
const props = defineProps<{
    open: boolean;
    filters: ShopFilters;
    total: number;
}>();

const emit = defineEmits<{
    close: [];
}>();

function update(patch: Partial<ShopFilters>): void {
    visitShop({ ...props.filters, ...patch });
}

function checked(event: Event): boolean {
    return (event.target as HTMLInputElement).checked;
}

const heading = 'mb-2.5 text-[15px] font-bold text-ink';
const option =
    'flex min-h-11 cursor-pointer items-center gap-3 text-base font-semibold text-ink';
const control = 'size-5 shrink-0 cursor-pointer accent-green dark:scheme-dark';
</script>

<template>
    <Sheet
        :open="props.open"
        side="bottom"
        label="Filters"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-6">
            <fieldset>
                <legend :class="heading">Price</legend>
                <div class="flex flex-wrap gap-2">
                    <ToggleChip
                        v-for="shortcut in PRICE_SHORTCUTS"
                        :key="shortcut.label"
                        :pressed="isShortcut(props.filters, shortcut)"
                        @click="
                            visitShop(withShortcut(props.filters, shortcut))
                        "
                    >
                        {{ shortcut.label }}
                    </ToggleChip>
                </div>
            </fieldset>

            <fieldset>
                <legend :class="heading">Minimum rating</legend>
                <div class="flex flex-wrap gap-2">
                    <ToggleChip
                        :pressed="props.filters.rating === ''"
                        @click="update({ rating: '' })"
                    >
                        Any
                    </ToggleChip>
                    <ToggleChip
                        v-for="stars in RATING_STEPS"
                        :key="stars"
                        :pressed="props.filters.rating === String(stars)"
                        @click="update({ rating: String(stars) })"
                    >
                        <span
                            aria-hidden="true"
                            :class="
                                props.filters.rating === String(stars)
                                    ? 'text-white'
                                    : 'text-star'
                            "
                            >★</span
                        >
                        {{ stars
                        }}<span class="sr-only">
                            {{ stars === 1 ? 'star' : 'stars' }}</span
                        >
                        and up
                    </ToggleChip>
                </div>
            </fieldset>

            <div class="grid border-t-[1.5px] border-line pt-3">
                <label :class="option">
                    <input
                        type="checkbox"
                        name="rated"
                        :checked="props.filters.rated"
                        :class="control"
                        @change="update({ rated: checked($event) })"
                    />
                    Only rated products
                </label>
                <label :class="option">
                    <input
                        type="checkbox"
                        name="featured"
                        :checked="props.filters.featured"
                        :class="control"
                        @change="update({ featured: checked($event) })"
                    />
                    Featured products only
                </label>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-3">
                <StallButton
                    variant="outline"
                    class="flex-1"
                    @click="visitShop(clearedFilters(props.filters))"
                >
                    Clear
                </StallButton>
                <StallButton
                    variant="green"
                    class="flex-[2]"
                    @click="emit('close')"
                >
                    Show {{ productCount(props.total) }}
                </StallButton>
            </div>
        </template>
    </Sheet>
</template>
