<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/common/app/InputError.vue';
import Button from '@/common/button/Button.vue';
import Dialog from '@/common/dialog/Dialog.vue';
import DialogContent from '@/common/dialog/DialogContent.vue';
import DialogDescription from '@/common/dialog/DialogDescription.vue';
import DialogFooter from '@/common/dialog/DialogFooter.vue';
import DialogHeader from '@/common/dialog/DialogHeader.vue';
import DialogTitle from '@/common/dialog/DialogTitle.vue';
import Label from '@/common/label/Label.vue';
import Select from '@/common/select/Select.vue';
import { useCurrentUser } from '@/modules/auth/useCurrentUser';
import { login } from '@/routes';
import { store } from '@/routes/checkout';
import { show as showWallet } from '@/routes/wallet';
import type { Cart, ShippingMethod } from '@/types';

const props = defineProps<{
    cart: Cart;
    shippingMethods: ShippingMethod[];
}>();

const page = usePage();
const user = useCurrentUser();
const flashError = computed(() => page.props.flash.error);
const balanceTooLow = computed(
    () => flashError.value === 'Your balance is too low for this order.',
);

const form = useForm({
    shipping_method_id: props.shippingMethods[0]?.id ?? 0,
});

const confirming = ref(false);

const selected = computed({
    get: () => String(form.shipping_method_id),
    set: (value: string) => {
        form.shipping_method_id = Number(value);
    },
});

const options = computed(() =>
    props.shippingMethods.map((method) => ({
        value: String(method.id),
        label: `${method.name} (${method.fee})`,
    })),
);

const method = computed(() =>
    props.shippingMethods.find((m) => m.id === form.shipping_method_id),
);

function submit(): void {
    form.submit(store(), {
        onFinish: () => {
            confirming.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-4">
        <template v-if="user">
            <div class="space-y-2">
                <Label for="shipping-method">Shipping method</Label>
                <Select
                    id="shipping-method"
                    v-model="selected"
                    :options="options"
                    placeholder="Choose shipping"
                />
                <InputError :message="form.errors.shipping_method_id" />
            </div>

            <p
                v-if="flashError"
                class="text-sm text-destructive"
                role="alert"
                data-test="checkout-error"
            >
                {{ flashError }}
            </p>
            <Link
                v-if="balanceTooLow"
                :href="showWallet()"
                class="block text-sm font-medium underline"
                data-test="top-up-link"
            >
                Top up your balance
            </Link>

            <Button
                class="w-full"
                :disabled="form.processing"
                data-test="checkout-button"
                @click="confirming = true"
            >
                Check out
            </Button>

            <Dialog v-model:open="confirming">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm your order</DialogTitle>
                        <DialogDescription>
                            Your balance will be charged
                            {{ props.cart.subtotal }} plus
                            {{ method?.fee }} shipping ({{ method?.name }}).
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" @click="confirming = false">
                            Cancel
                        </Button>
                        <Button
                            :disabled="form.processing"
                            data-test="confirm-checkout"
                            @click="submit"
                        >
                            Place order
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>

        <template v-else>
            <p
                v-if="flashError"
                class="text-sm text-destructive"
                role="alert"
                data-test="checkout-error"
            >
                {{ flashError }}
            </p>
            <Button class="w-full" as-child>
                <Link :href="login()" data-test="login-to-checkout">
                    Log in to check out
                </Link>
            </Button>
        </template>
    </div>
</template>
