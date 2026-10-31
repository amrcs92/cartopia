import { router } from '@inertiajs/vue3';
import { index as productsIndex } from '@/routes/products';
import type { ProductFilterValues, SelectOption } from '@/types';

// The shop's filters, shared by the sidebar, the phone sheet, the aisle chip
// row and the no-results page. The page's raw `filters` prop is normalised
// into `ShopFilters`; any change is a fresh visit to the shop with only the
// non-empty keys (q, aisle[], min_price, max_price, rating, rated, featured,
// sort), so the server stays the one source of truth and the URL can be shared.

export type ShopFilters = {
    q: string;
    aisle: string[];
    min_price: string;
    max_price: string;
    /** '' means any rating. */
    rating: string;
    rated: boolean;
    featured: boolean;
    sort: string;
};

export const DEFAULT_SORT = 'newest';

export const SORT_OPTIONS: SelectOption[] = [
    { value: 'newest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'rating', label: 'Top rated' },
];

export type PriceShortcut = { label: string; min: string; max: string };

export const PRICE_SHORTCUTS: PriceShortcut[] = [
    { label: 'Under $1', min: '', max: '1' },
    { label: '$1 to $2', min: '1', max: '2' },
    { label: '$2 to $5', min: '2', max: '5' },
];

// The minimum-rating options after "Any", shared by the sidebar and the sheet.
export const RATING_STEPS = [4, 3, 2, 1] as const;

const TRUE_VALUES = ['1', 'true', 'on', 'yes'];

function text(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

export function normaliseFilters(raw: ProductFilterValues): ShopFilters {
    const aisle = Array.isArray(raw.aisle)
        ? raw.aisle.filter((slug): slug is string => typeof slug === 'string')
        : [];

    return {
        q: text(raw.q),
        aisle: [...new Set(aisle)],
        min_price: text(raw.min_price),
        max_price: text(raw.max_price),
        rating: text(raw.rating),
        rated: TRUE_VALUES.includes(text(raw.rated).toLowerCase()),
        featured: TRUE_VALUES.includes(text(raw.featured).toLowerCase()),
        sort: text(raw.sort) || DEFAULT_SORT,
    };
}

export function toQuery(
    filters: ShopFilters,
): Record<string, string | string[]> {
    const query: Record<string, string | string[]> = {};
    const q = filters.q.trim();
    const min = filters.min_price.trim();
    const max = filters.max_price.trim();

    if (q !== '') {
        query.q = q;
    }

    if (filters.aisle.length > 0) {
        query.aisle = filters.aisle;
    }

    if (min !== '') {
        query.min_price = min;
    }

    if (max !== '') {
        query.max_price = max;
    }

    if (filters.rating !== '') {
        query.rating = filters.rating;
    }

    if (filters.rated) {
        query.rated = '1';
    }

    if (filters.featured) {
        query.featured = '1';
    }

    if (filters.sort !== DEFAULT_SORT) {
        query.sort = filters.sort;
    }

    return query;
}

/** Visits the shop (page 1) with the given filters. */
export function visitShop(filters: ShopFilters): void {
    router.get(productsIndex.url(), toQuery(filters), {
        preserveScroll: true,
        preserveState: true,
    });
}

/** The same filters with everything but the sort order cleared. */
export function clearedFilters(filters: ShopFilters): ShopFilters {
    return {
        q: '',
        aisle: [],
        min_price: '',
        max_price: '',
        rating: '',
        rated: false,
        featured: false,
        sort: filters.sort,
    };
}

export function hasPriceFilter(filters: ShopFilters): boolean {
    return filters.min_price !== '' || filters.max_price !== '';
}

// '1' and '1.00' are the same price; '' (no bound) only matches ''.
function samePrice(a: string, b: string): boolean {
    return a === '' || b === '' ? a === b : Number(a) === Number(b);
}

/** Whether a price shortcut matches the current min and max. */
export function isShortcut(
    filters: ShopFilters,
    shortcut: PriceShortcut,
): boolean {
    return (
        samePrice(filters.min_price, shortcut.min) &&
        samePrice(filters.max_price, shortcut.max)
    );
}

/** Ticking a selected shortcut again clears the price. */
export function withShortcut(
    filters: ShopFilters,
    shortcut: PriceShortcut,
): ShopFilters {
    return isShortcut(filters, shortcut)
        ? { ...filters, min_price: '', max_price: '' }
        : { ...filters, min_price: shortcut.min, max_price: shortcut.max };
}

export function ratingLabel(stars: number): string {
    return `${stars} ${stars === 1 ? 'star' : 'stars'} and up`;
}

export function productCount(total: number): string {
    return `${total} ${total === 1 ? 'product' : 'products'}`;
}
