<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import Sheet from '@/common/sheet/Sheet.vue';
import IconButton from '@/common/stall/IconButton.vue';
import PriceTag from '@/common/stall/PriceTag.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { addToCart } from '@/modules/cart/useCartActions';
import { show as showCart } from '@/routes/cart';
import type { AddedToBasket } from '@/types';

// Opens whenever the add-to-basket redirect flashes `addedToBasket`. The last
// payload is kept while the panel slides out (the flash is already gone by
// then). Adding a suggestion flashes a fresh payload, so the panel updates in
// place. `flash` can be missing (a not-found page has no shared props).
const page = usePage();
const added = computed(() => page.props.flash?.addedToBasket ?? null);

const shown = ref<AddedToBasket | null>(null);
const open = ref(false);

watch(
    added,
    (value) => {
        if (value) {
            shown.value = value;
            open.value = true;
        }
    },
    { immediate: true },
);

function close(): void {
    open.value = false;
}
</script>

<template>
    <Sheet
        :open="open"
        side="right"
        label="Added to your basket"
        class="max-sm:w-full max-sm:rounded-none max-sm:border-l-0"
        @close="close"
    >
        <div v-if="shown" class="flex flex-col gap-6">
            <div
                class="flex items-center gap-4 rounded-[18px] border-[1.5px] border-line bg-well p-3"
                data-test="added-item"
            >
                <span
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface"
                >
                    <img
                        v-if="shown.product.image_url"
                        :src="shown.product.image_url"
                        alt=""
                        class="max-h-full max-w-full object-contain mix-blend-multiply"
                    />
                    <Icon
                        v-else
                        name="basket"
                        :size="32"
                        class="text-on-marigold opacity-30"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold wrap-anywhere text-ink">
                        {{ shown.quantity }} &times; {{ shown.product.name }}
                    </p>
                    <p class="font-serif text-[22px] text-ink tabular-nums">
                        {{ shown.line_total }}
                    </p>
                </div>
            </div>

            <p class="text-body" data-test="basket-summary">
                Your basket has
                <strong class="text-ink">
                    {{ shown.basket.count }}
                    {{ shown.basket.count === 1 ? 'item' : 'items' }}
                </strong>
                totalling
                <strong class="text-ink tabular-nums">
                    {{ shown.basket.subtotal }}
                </strong>
                .
            </p>

            <section
                v-if="shown.suggestions.length > 0"
                aria-labelledby="goes-well-with"
                class="flex flex-col gap-3"
            >
                <h3 id="goes-well-with" class="font-serif text-[20px] text-ink">
                    Goes well with
                </h3>
                <ul class="flex flex-col gap-3">
                    <li
                        v-for="product in shown.suggestions"
                        :key="product.id"
                        class="flex items-center gap-3 rounded-[14px] border-[1.5px] border-line p-2.5"
                    >
                        <span
                            class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-well"
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
                                :size="24"
                                class="text-on-marigold opacity-30"
                            />
                        </span>
                        <p
                            class="min-w-0 flex-1 text-[15px] leading-tight font-bold wrap-anywhere text-ink"
                        >
                            {{ product.name }}
                        </p>
                        <PriceTag :price="product.price" />
                        <IconButton
                            :label="`Quick add ${product.name}`"
                            icon="basket-add"
                            variant="marigold"
                            @click="addToCart(product.id)"
                        />
                    </li>
                </ul>
            </section>
        </div>

        <template #footer>
            <div class="flex flex-col gap-3">
                <StallButton
                    as="link"
                    variant="green"
                    size="lg"
                    :href="showCart().url"
                    @click="close"
                >
                    View basket and check out
                </StallButton>
                <StallButton variant="outline" @click="close">
                    Keep shopping
                </StallButton>
            </div>
        </template>
    </Sheet>
</template>
