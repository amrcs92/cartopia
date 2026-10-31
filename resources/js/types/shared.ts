import type { User } from './auth';

export type Flash = {
    success: string | null;
    error: string | null;
};

export type SharedProps = {
    name: string;
    auth: {
        user: User | null;
    };
    cart: {
        count: number;
    };
    flash: Flash;
    sidebarOpen: boolean;
};
