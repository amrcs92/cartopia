<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef } from 'vue';
import { login } from '@/routes';
import { update } from '@/routes/products/rating';

// "Your rating": five 48px stars in a radio group. A star fills marigold up
// to your rating (or up to the star you're pointing at), and every star keeps
// its outline, so the value never rests on colour alone. Pressing a star
// saves it; the arrow keys move between stars (one tab stop for the group).
// Guests get a link to log in instead.
const props = defineProps<{
    slug: string;
    name: string;
    myRating: number | null;
}>();

const page = usePage();
const authenticated = computed(() => page.props.auth.user !== null);
const stars = [1, 2, 3, 4, 5];

const hovered = ref<number | null>(null);
const shown = computed(() => hovered.value ?? props.myRating ?? 0);
const tabStop = computed(() => props.myRating ?? 1);

const buttons = useTemplateRef<HTMLButtonElement[]>('buttons');

function rate(value: number): void {
    router.put(
        update(props.slug),
        { stars: value },
        { preserveScroll: true, only: ['product', 'flash'] },
    );
}

function move(from: number, by: number): void {
    const to = Math.min(5, Math.max(1, from + by));

    buttons.value?.[to - 1]?.focus();
}

const STAR =
    'M12 2.8l2.85 5.78 6.38.93-4.62 4.5 1.09 6.35L12 17.36l-5.7 3 1.09-6.35-4.62-4.5 6.38-.93z';
</script>

<template>
    <div v-if="authenticated" class="flex flex-col gap-3">
        <div
            role="radiogroup"
            :aria-label="`Rate ${props.name}`"
            class="flex gap-1"
            @mouseleave="hovered = null"
        >
            <button
                v-for="star in stars"
                ref="buttons"
                :key="star"
                type="button"
                role="radio"
                :aria-label="`${star} ${star === 1 ? 'star' : 'stars'}`"
                :aria-checked="props.myRating === star"
                :tabindex="star === tabStop ? 0 : -1"
                class="inline-flex size-12 items-center justify-center rounded-xl transition-colors duration-200 ease-stall hover:bg-field"
                @click="rate(star)"
                @mouseenter="hovered = star"
                @keydown.right.prevent="move(star, 1)"
                @keydown.down.prevent="move(star, 1)"
                @keydown.left.prevent="move(star, -1)"
                @keydown.up.prevent="move(star, -1)"
                @keydown.home.prevent="move(star, -5)"
                @keydown.end.prevent="move(star, 5)"
            >
                <svg
                    viewBox="0 0 24 24"
                    width="40"
                    height="40"
                    aria-hidden="true"
                    focusable="false"
                    class="text-outline"
                >
                    <path
                        :d="STAR"
                        :class="shown >= star ? 'fill-marigold' : 'fill-none'"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>
        </div>
        <p class="text-[15px] font-semibold text-body">
            <template v-if="props.myRating !== null">
                You rated this {{ props.myRating }} out of 5. Pick a star to
                change it.
            </template>
            <template v-else>Pick a star to rate it.</template>
        </p>
    </div>
    <Link
        v-else
        :href="login()"
        class="inline-flex min-h-11 items-center text-base font-bold text-link underline underline-offset-4"
    >
        Log in to rate this product
    </Link>
</template>
