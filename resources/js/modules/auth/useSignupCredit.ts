import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

// The credit new accounts get, as the copy says it ("$100"), set by admins in
// the panel. Null when it's turned off, and the copy promising it is hidden.
export function useSignupCredit(): ComputedRef<string | null> {
    const page = usePage();

    return computed(() => page.props.signupCredit ?? null);
}
