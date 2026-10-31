import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        // The added-to-basket panel already confirms the add.
        if (!data || flash?.addedToBasket) {
            return;
        }

        toast[data.type](data.message);
    });
}
