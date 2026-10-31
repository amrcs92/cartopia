export type Product = {
    id: number;
    name: string;
    slug: string;
    price: string;
    image_url: string | null;
    rating_average: number;
    rating_count: number;
};

export type ProductDetail = Product & {
    description: string;
    images: string[];
    my_rating: number | null;
};

export type ProductFilterValues = {
    q: string | null;
    min_price: string | null;
    max_price: string | null;
    rating: string | null;
    sort: string | null;
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
