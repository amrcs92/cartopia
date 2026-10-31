<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import PriceTag from '@/common/stall/PriceTag.vue';
import StallButton from '@/common/stall/StallButton.vue';
import {
    addToCart,
    clampQuantity,
    MAX_QUANTITY,
    MIN_QUANTITY,
} from '@/modules/cart/useCartActions';
import FeaturedCarousel from '@/modules/products/FeaturedCarousel.vue';
import RatingInput from '@/modules/products/RatingInput.vue';
import StarRating from '@/modules/products/StarRating.vue';
import { index as productsIndex } from '@/routes/products';
import type { Product, ProductDetail } from '@/types';

// One product: the breadcrumb (Shop / aisle / product, without the aisle when
// it has none), the photo panel with thumbnails when there are several, the
// name, rating line, large price tag and description, the quantity stepper
// with "Add to basket", "Your rating", then the featured carousel (which the
// server already sends without this product).
const props = defineProps<{
    product: ProductDetail;
    featured: Product[];
}>();

const selected = ref(0);
const quantity = ref(1);
const currentImage = computed(
    () => props.product.images[selected.value] ?? props.product.image_url,
);

function setQuantity(value: number): void {
    quantity.value = clampQuantity(value);
}

function onQuantityInput(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.value !== '') {
        setQuantity(Number(input.value));
    }

    // Vue skips the patch when a clamped value equals the old one.
    input.value = String(quantity.value);
}

const stepButton =
    'inline-flex w-12 shrink-0 items-center justify-center text-ink transition-colors duration-200 ease-stall hover:bg-field disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent';
</script>

<template>
    <Head :title="props.product.name" />

    <div class="flex flex-col gap-8 pt-1 pb-6 sm:pt-4 lg:gap-14 lg:pb-12">
        <div class="flex flex-col gap-6 lg:gap-8">
            <nav aria-label="Breadcrumb">
                <ol
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[15px] font-semibold text-subtle"
                >
                    <li>
                        <Link
                            :href="productsIndex()"
                            class="rounded-md underline-offset-4 hover:text-ink hover:underline"
                        >
                            Shop
                        </Link>
                    </li>
                    <template v-if="props.product.category">
                        <li aria-hidden="true">/</li>
                        <li>
                            <Link
                                :href="
                                    productsIndex.url({
                                        query: {
                                            aisle: [
                                                props.product.category.slug,
                                            ],
                                        },
                                    })
                                "
                                class="rounded-md underline-offset-4 hover:text-ink hover:underline"
                            >
                                {{ props.product.category.name }}
                            </Link>
                        </li>
                    </template>
                    <li aria-hidden="true">/</li>
                    <li
                        class="min-w-0 wrap-anywhere text-ink"
                        aria-current="page"
                    >
                        {{ props.product.name }}
                    </li>
                </ol>
            </nav>

            <div
                class="grid grid-cols-1 items-start gap-8 lg:grid-cols-2 lg:gap-12"
            >
                <div class="flex min-w-0 flex-col gap-3">
                    <div
                        class="flex aspect-square items-center justify-center overflow-hidden rounded-[28px] border-[2.5px] border-outline bg-well p-6 shadow-panel sm:p-10"
                    >
                        <img
                            v-if="currentImage"
                            :src="currentImage"
                            :alt="props.product.name"
                            class="max-h-full max-w-full object-contain mix-blend-multiply"
                        />
                        <Icon
                            v-else
                            name="basket"
                            :size="96"
                            class="text-on-marigold opacity-30"
                        />
                    </div>

                    <div
                        v-if="props.product.images.length > 1"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="(image, index) in props.product.images"
                            :key="image"
                            type="button"
                            class="size-16 overflow-hidden rounded-xl bg-well p-1 transition-colors duration-200 ease-stall"
                            :class="
                                index === selected
                                    ? 'border-[2.5px] border-outline'
                                    : 'border-[1.5px] border-line-strong hover:border-outline'
                            "
                            :aria-label="`Show image ${index + 1}`"
                            :aria-pressed="index === selected"
                            @click="selected = index"
                        >
                            <img
                                :src="image"
                                alt=""
                                class="size-full object-contain mix-blend-multiply"
                            />
                        </button>
                    </div>
                </div>

                <div class="flex min-w-0 flex-col items-start gap-5">
                    <h1
                        class="font-serif text-[36px] leading-[1.08] font-normal tracking-[-0.5px] wrap-anywhere text-ink lg:text-[60px] lg:tracking-[-1px]"
                    >
                        {{ props.product.name }}
                    </h1>
                    <StarRating
                        :average="props.product.rating_average"
                        :count="props.product.rating_count"
                    />
                    <PriceTag
                        :price="props.product.price"
                        size="lg"
                        data-test="price"
                    />
                    <p
                        class="text-base leading-[1.65] whitespace-pre-line text-body lg:text-[17px]"
                    >
                        {{ props.product.description }}
                    </p>

                    <div class="flex w-full flex-wrap items-center gap-3">
                        <div
                            class="inline-flex h-[58px] items-stretch overflow-hidden rounded-2xl border-[2.5px] border-outline bg-surface"
                        >
                            <button
                                type="button"
                                :class="stepButton"
                                :disabled="quantity <= MIN_QUANTITY"
                                :aria-label="`Decrease quantity of ${props.product.name}`"
                                @click="setQuantity(quantity - 1)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="text-2xl leading-none font-bold"
                                    >&minus;</span
                                >
                            </button>
                            <input
                                :value="quantity"
                                type="number"
                                inputmode="numeric"
                                :min="MIN_QUANTITY"
                                :max="MAX_QUANTITY"
                                :aria-label="`Quantity of ${props.product.name}`"
                                class="w-12 [appearance:textfield] bg-transparent text-center text-lg font-extrabold text-ink tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                @change="onQuantityInput"
                            />
                            <button
                                type="button"
                                :class="stepButton"
                                :disabled="quantity >= MAX_QUANTITY"
                                :aria-label="`Increase quantity of ${props.product.name}`"
                                @click="setQuantity(quantity + 1)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="text-2xl leading-none font-bold"
                                    >+</span
                                >
                            </button>
                        </div>
                        <StallButton
                            variant="marigold"
                            size="lg"
                            class="min-h-[58px] flex-1 sm:flex-none"
                            @click="addToCart(props.product.id, quantity)"
                        >
                            <Icon name="basket-add" :size="22" />
                            Add to basket
                        </StallButton>
                    </div>

                    <section
                        aria-labelledby="your-rating"
                        class="flex w-full flex-col gap-3 rounded-[22px] border-[1.5px] border-line bg-surface p-5 sm:p-6"
                    >
                        <h2
                            id="your-rating"
                            class="font-serif text-[22px] leading-tight font-normal text-ink lg:text-[24px]"
                        >
                            Your rating
                        </h2>
                        <RatingInput
                            :slug="props.product.slug"
                            :name="props.product.name"
                            :my-rating="props.product.my_rating"
                        />
                    </section>
                </div>
            </div>
        </div>

        <!-- The hanging sign rises about 58px above the carousel panel, so
             the padding leaves the mockup's 56px clear above the sign. -->
        <div v-if="props.featured.length > 0" class="pt-12 lg:pt-14">
            <FeaturedCarousel :products="props.featured" />
        </div>
    </div>
</template>
