<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/common/icon/Icon.vue';
import PriceTag from '@/common/stall/PriceTag.vue';
import StallButton from '@/common/stall/StallButton.vue';
import QuantityStepper from '@/modules/cart/QuantityStepper.vue';
import { addToCart } from '@/modules/cart/useCartActions';
import FeaturedCarousel from '@/modules/products/FeaturedCarousel.vue';
import ProductGallery from '@/modules/products/ProductGallery.vue';
import ProductZoomPanel from '@/modules/products/ProductZoomPanel.vue';
import RatingInput from '@/modules/products/RatingInput.vue';
import StarRating from '@/modules/products/StarRating.vue';
import { index as productsIndex } from '@/routes/products';
import type { Product, ProductDetail } from '@/types';

// One product: the breadcrumb (Shop / aisle / product, without the aisle when
// it has none), the photo gallery (ProductGallery), the
// name, rating line, large price tag and description, the quantity stepper
// with "Add to basket", "Your rating", then the featured carousel (which the
// server already sends without this product). While the mouse is on the
// photo, the zoom panel covers the details column with the marked area.
const props = defineProps<{
    product: ProductDetail;
    featured: Product[];
}>();

const selected = ref(0);
const quantity = ref(1);
const zoom = ref<{ x: number; y: number } | null>(null);
const images = computed(() =>
    props.product.images.length > 0
        ? props.product.images
        : props.product.image_url
          ? [props.product.image_url]
          : [],
);
const currentImage = computed(() => images.value[selected.value] ?? null);
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
                <ProductGallery
                    v-model:index="selected"
                    v-model:zoom="zoom"
                    :images="images"
                    :name="props.product.name"
                />

                <div class="relative min-w-0">
                    <ProductZoomPanel
                        v-if="zoom && currentImage"
                        :image="currentImage"
                        :zoom="zoom"
                    />
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
                            <QuantityStepper
                                v-model="quantity"
                                :label="props.product.name"
                                size="lg"
                            />
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
        </div>

        <!-- The hanging sign rises about 58px above the carousel panel, so
             the padding leaves the mockup's 56px clear above the sign. -->
        <div v-if="props.featured.length > 0" class="pt-12 lg:pt-14">
            <FeaturedCarousel :products="props.featured" />
        </div>
    </div>
</template>
