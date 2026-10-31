import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { User } from '@/types';

// `auth` is missing on a not-found page for an unmatched URL, which is
// rendered without the shared props; that reads as a guest.
export function useCurrentUser(): ComputedRef<User | null> {
    const page = usePage();

    return computed(() => page.props.auth?.user ?? null);
}
