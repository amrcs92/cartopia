<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/common/app/InputError.vue';
import TextLink from '@/common/app/TextLink.vue';
import StallButton from '@/common/stall/StallButton.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import { Spinner } from '@/lib/shadcn/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        role="status"
        class="mb-4 rounded-xl border-2 border-green bg-chip px-4 py-3 text-sm font-bold text-chip-ink"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <StallButton
                    variant="marigold"
                    size="lg"
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Email password reset link
                </StallButton>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Or, return to</span>
            <TextLink :href="login()">log in</TextLink>
        </div>
    </div>
</template>
