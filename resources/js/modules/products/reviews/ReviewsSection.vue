<script setup lang="ts">
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DialogContent from '@/common/dialog/DialogContent.vue';
import SectionHeading from '@/common/stall/SectionHeading.vue';
import StallButton from '@/common/stall/StallButton.vue';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/lib/shadcn/dialog';
import StarRating from '@/modules/products/StarRating.vue';
import ReviewForm from '@/modules/products/reviews/ReviewForm.vue';
import ReviewItem from '@/modules/products/reviews/ReviewItem.vue';
import { login } from '@/routes';
import { destroy } from '@/routes/products/review';
import type { ProductDetail, ProductReviews, Review } from '@/types';

// "Reviews" (#reviews) under the product: the average and count, then the
// viewer's part (a log-in line for guests, a note for customers who didn't
// buy it, the form for buyers, or their own review with Edit and Delete),
// then the written reviews, five at a time, with "Show more reviews".
const props = defineProps<{
    product: ProductDetail;
    reviews: ProductReviews;
    reviewList: { data: Review[] };
}>();

const editing = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);

// A new or deleted review comes back as a new `mine`; leave edit mode then.
watch(
    () => props.reviews.mine?.id,
    () => {
        editing.value = false;
    },
);

function deleteReview(): void {
    router.delete(destroy(props.product.slug).url, {
        preserveScroll: true,
        only: ['product', 'reviews'],
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
}
</script>

<template>
    <section
        id="reviews"
        aria-label="Reviews"
        class="flex scroll-mt-[calc(var(--store-header-h,0px)+16px)] flex-col gap-6"
    >
        <div class="flex flex-col gap-2">
            <SectionHeading title="Reviews" />
            <StarRating
                :average="props.product.rating_average"
                :count="props.product.rating_count"
            />
        </div>

        <div
            class="flex flex-col gap-4 rounded-[22px] border-[1.5px] border-line bg-surface p-5 sm:p-6 lg:max-w-3xl"
        >
            <p
                v-if="props.reviews.can_review === 'guest'"
                class="text-base text-body"
            >
                <Link
                    :href="login()"
                    class="font-bold text-link underline underline-offset-4"
                    >Log in</Link
                >
                to review this product.
            </p>
            <p
                v-else-if="
                    props.reviews.can_review === 'not_buyer' &&
                    !props.reviews.mine
                "
                class="text-base text-body"
            >
                Only customers who bought this product can review it.
            </p>
            <template v-else-if="props.reviews.mine && !editing">
                <h3 class="font-serif text-[22px] leading-tight text-ink">
                    Your review
                </h3>
                <div data-test="my-review">
                    <ReviewItem :review="props.reviews.mine" />
                </div>
                <div
                    v-if="props.reviews.can_review === 'yes'"
                    class="flex flex-wrap gap-3"
                >
                    <StallButton variant="outline" @click="editing = true">
                        Edit
                    </StallButton>
                    <StallButton
                        variant="quiet"
                        @click="confirmingDelete = true"
                    >
                        Delete
                    </StallButton>
                </div>
            </template>
            <template v-else-if="props.reviews.can_review === 'yes'">
                <h3 class="font-serif text-[22px] leading-tight text-ink">
                    {{
                        props.reviews.mine
                            ? 'Edit your review'
                            : 'Write a review'
                    }}
                </h3>
                <ReviewForm
                    :key="props.reviews.mine?.id ?? 'new'"
                    :slug="props.product.slug"
                    :product-name="props.product.name"
                    :review="props.reviews.mine"
                    @saved="editing = false"
                    @cancel="editing = false"
                />
            </template>
        </div>

        <InfiniteScroll
            data="reviewList"
            manual
            only-next
            preserve-url
            items-element="[data-test=review-list]"
            class="flex flex-col gap-5 lg:max-w-3xl"
        >
            <ul
                v-if="props.reviewList.data.length > 0"
                data-test="review-list"
                class="flex flex-col divide-y-[1.5px] divide-line"
            >
                <li
                    v-for="review in props.reviewList.data"
                    :key="review.id"
                    class="py-5 first:pt-0"
                >
                    <ReviewItem :review="review" />
                </li>
            </ul>
            <p v-else class="text-base text-body">No written reviews yet.</p>

            <template #next="{ fetch, hasMore, loading }">
                <StallButton
                    v-if="hasMore"
                    variant="outline"
                    class="self-start"
                    :disabled="loading"
                    @click="fetch"
                >
                    Show more reviews
                </StallButton>
            </template>
        </InfiniteScroll>

        <Dialog v-model:open="confirmingDelete">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete your review?</DialogTitle>
                    <DialogDescription>
                        Your stars and text will be removed from this product.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <StallButton variant="quiet">Keep it</StallButton>
                    </DialogClose>
                    <StallButton
                        variant="marigold"
                        data-test="confirm-delete-review"
                        :disabled="deleting"
                        @click="deleteReview"
                    >
                        Delete
                    </StallButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
