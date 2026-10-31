import type { Product } from './catalog';

export type CartLine = {
    product: Product;
    quantity: number;
    unit_price: string;
    line_total: string;
};

export type Cart = {
    count: number;
    lines: CartLine[];
    subtotal: string;
    total_quantity: number;
};

export type ShippingMethod = {
    id: number;
    name: string;
    fee: string;
    fee_cents: number;
};
