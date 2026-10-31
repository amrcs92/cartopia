import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';
import type { User } from '@/types';

export type StoreSection =
    | 'home'
    | 'shop'
    | 'basket'
    | 'orders'
    | 'wallet'
    | 'account'
    | 'login'
    | 'register'
    | null;

const sections: [RegExp, Exclude<StoreSection, null>][] = [
    [/^\/$/, 'home'],
    [/^\/products(\/|$)/, 'shop'],
    [/^\/(cart|checkout)(\/|$)/, 'basket'],
    [/^\/orders(\/|$)/, 'orders'],
    [/^\/wallet(\/|$)/, 'wallet'],
    [/^\/settings(\/|$)/, 'account'],
    [/^\/login(\/|$)/, 'login'],
    [/^\/register(\/|$)/, 'register'],
];

export function sectionFor(url: string): StoreSection {
    const path = url.split(/[?#]/)[0];

    return sections.find(([pattern]) => pattern.test(path))?.[1] ?? null;
}

/**
 * What the store chrome needs from the shared props. A not-found page for an
 * unmatched URL is rendered without the shared props, so a missing `auth` or
 * `cart` reads as a guest with an empty basket.
 */
export function useStoreChrome(): {
    user: ComputedRef<User | null>;
    basketCount: ComputedRef<number>;
    section: ComputedRef<StoreSection>;
} {
    const page = usePage();

    return {
        user: useCurrentUser(),
        basketCount: computed(() => page.props.cart?.count ?? 0),
        section: computed(() => sectionFor(page.url)),
    };
}
