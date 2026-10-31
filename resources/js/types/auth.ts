/**
 * The signed-in user as shared by HandleInertiaRequests (`auth.user`).
 */
export type User = {
    id: number;
    name: string;
    email: string;
    balance: string;
    balance_cents: number;
    is_admin: boolean;
    avatar?: string;
};

export type Auth = {
    user: User | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
