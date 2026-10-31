import type { User } from './auth';
import type { Aisle, Product } from './catalog';

/**
 * What was just put in the basket (the `addedToBasket` flash), for the
 * added-to-basket panel.
 */
export type AddedToBasket = {
    product: Pick<Product, 'id' | 'name' | 'image_url'>;
    quantity: number;
    line_total: string;
    basket: {
        count: number;
        subtotal: string;
    };
    suggestions: Product[];
};

export type SharedProps = {
    name: string;
    auth: {
        user: User | null;
    };
    cart: {
        count: number;
    };
    sidebarOpen: boolean;
    /** Sent only on request (a partial reload), for the phone menu and footer. */
    menuAisles?: Aisle[];
};
