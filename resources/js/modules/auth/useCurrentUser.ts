import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { User } from '@/types';

export function useCurrentUser(): ComputedRef<User | null> {
    const page = usePage();

    return computed(() => page.props.auth.user);
}
