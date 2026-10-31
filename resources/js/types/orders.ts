export type OrderItem = {
    product_name: string;
    product_slug: string | null;
    unit_price: string;
    quantity: number;
    line_total: string;
};

export type Order = {
    id: number;
    status: string;
    subtotal: string;
    shipping_fee: string;
    total: string;
    shipping_method: string;
    placed_at: string;
    items: OrderItem[];
};
