export type OrderItem = {
    product_name: string;
    product_slug: string | null;
    unit_price: string;
    quantity: number;
    line_total: string;
};

export type OrderPayment = {
    method: 'wallet' | 'stripe' | 'paypal';
    paid_with: string;
    /** Shortened for display; `full` is the whole sentence with the id. */
    reference: { short: string; full: string } | null;
    slip_lines: { label: string; value: string }[];
    tag: string;
};

export type Order = {
    id: number;
    status: string;
    subtotal: string;
    shipping_fee: string;
    total: string;
    shipping_method: string;
    placed_at: string;
    /** Up to two product photo URLs (first two lines of the order). */
    thumbnails: string[];
    payment: OrderPayment;
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
