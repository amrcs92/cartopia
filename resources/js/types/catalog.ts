export type Product = {
    id: number;
    name: string;
    /** A shorter name for the home page's price board; null to use `name`. */
    short_name: string | null;
    slug: string;
    price: string;
    image_url: string | null;
    rating_average: number;
    rating_count: number;
    /** The aisle's name; only sent where the aisle is loaded (the featured carousel). */
    aisle?: string | null;
};

export type ProductDetail = Product & {
    description: string;
    /** The product's aisle, for the breadcrumb; null when it has none. */
    category: { name: string; slug: string } | null;
    images: string[];
    my_rating: number | null;
};

/**
 * The shop's filter inputs exactly as they arrived in the query string, so
 * any of them can be missing, or (from a hand-edited URL) the wrong shape.
 */
export type ProductFilterValues = {
    q: string | null;
    min_price: string | null;
    max_price: string | null;
    rating: string | null;
    sort: string | null;
    aisle: string[] | string | null;
    rated: string | null;
    featured: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

/**
 * An aisle (a product category) as served by AisleResource.
 */
export type Aisle = {
    id: number;
    name: string;
    slug: string;
    product_count: number;
    image_url: string | null;
};
