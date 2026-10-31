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

export type WalletTransaction = {
    id: number;
    type: string;
    type_label: string;
    amount: string;
    balance_after: string;
    description: string | null;
    created_at: string;
};
