<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import { login } from '@/routes';
import { update } from '@/routes/products/rating';

const props = defineProps<{
    slug: string;
    myRating: number | null;
}>();

const page = usePage();
const authenticated = computed(() => page.props.auth.user !== null);
const stars = [1, 2, 3, 4, 5];

function rate(value: number): void {
    router.put(
        update(props.slug),
        { stars: value },
        { preserveScroll: true, only: ['product', 'flash'] },
    );
}
</script>

<template>
    <div class="space-y-1">
        <div v-if="authenticated" class="flex items-center gap-1">
            <button
                v-for="star in stars"
                :key="star"
                type="button"
                class="rounded p-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="`Rate ${star} ${star === 1 ? 'star' : 'stars'}`"
                :aria-pressed="props.myRating === star"
                @click="rate(star)"
            >
                <Star
                    class="size-6"
                    :class="
                        (props.myRating ?? 0) >= star
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-muted-foreground/40 hover:text-amber-400'
                    "
                    aria-hidden="true"
                />
            </button>
        </div>
        <Link
            v-else
            :href="login()"
            class="text-sm text-primary underline-offset-4 hover:underline"
        >
            Log in to rate
        </Link>
    </div>
</template>
