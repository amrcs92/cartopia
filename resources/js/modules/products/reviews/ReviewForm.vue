<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/common/app/InputError.vue';
import Label from '@/common/label/Label.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { Spinner } from '@/lib/shadcn/spinner';
import StarPicker from '@/modules/products/reviews/StarPicker.vue';
import { update } from '@/routes/products/review';
import type { Review } from '@/types';

// Posting or editing your review: the stars (required) and up to 1,000
// characters of text. "Post review" (or "Save" when editing) stays disabled
// until a star is chosen; editing also offers "Cancel".
const MAX_BODY = 1000;

const props = defineProps<{
    slug: string;
    productName: string;
    review: Review | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const form = useForm<{ stars: number | null; body: string }>({
    stars: props.review?.stars ?? null,
    body: props.review?.body ?? '',
});

function submit(): void {
    form.put(update(props.slug).url, {
        preserveScroll: true,
        only: ['product', 'reviews'],
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-2">
            <span class="text-base font-bold text-ink">Your stars</span>
            <StarPicker
                v-model="form.stars"
                :label="`Rate ${props.productName}`"
            />
            <InputError :message="form.errors.stars" />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="review-body">Your review (optional)</Label>
            <textarea
                id="review-body"
                v-model="form.body"
                rows="4"
                :maxlength="MAX_BODY"
                :aria-invalid="form.errors.body ? true : undefined"
                aria-describedby="review-body-count"
                class="min-h-28 w-full rounded-xl border-2 border-line-strong bg-field px-4 py-3 text-base text-ink transition-colors duration-200 ease-stall placeholder:text-subtle hover:border-outline focus-visible:border-outline focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-marigold focus-visible:outline-solid aria-invalid:border-danger"
                placeholder="What did you think of it?"
            />
            <div class="flex items-start justify-between gap-3">
                <InputError :message="form.errors.body" />
                <span
                    id="review-body-count"
                    class="ml-auto text-sm text-subtle tabular-nums"
                    >{{ form.body.length }}/{{ MAX_BODY }}</span
                >
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <StallButton
                variant="marigold"
                type="submit"
                :disabled="form.stars === null || form.processing"
            >
                <Spinner v-if="form.processing" />
                {{ props.review ? 'Save' : 'Post review' }}
            </StallButton>
            <StallButton
                v-if="props.review"
                variant="quiet"
                type="button"
                @click="emit('cancel')"
            >
                Cancel
            </StallButton>
        </div>
    </form>
</template>
