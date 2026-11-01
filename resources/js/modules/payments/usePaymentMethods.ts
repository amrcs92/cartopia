import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { HostedMethod } from '@/modules/payments/methods';

// The hosted methods with credentials configured on this copy of the store.
export function usePaymentMethods(): ComputedRef<HostedMethod[]> {
    const page = usePage();

    return computed(() => page.props.paymentMethods ?? []);
}
