<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Heading from '@/common/app/Heading.vue';
import InputError from '@/common/app/InputError.vue';
import PasswordInput from '@/common/app/PasswordInput.vue';
import StallButton from '@/common/stall/StallButton.vue';
import Label from '@/common/label/Label.vue';
import { Spinner } from '@/lib/shadcn/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/common/app/PasskeyVerify.vue';
import { edit as editSecurity } from '@/routes/security';

// Shown on the way into Security, inside the store and settings layouts with
// Security still selected, so it reads as a step of that page.
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: editSecurity(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Confirm password" />

    <Heading
        title="Confirm password"
        description="This is a secure area of the application. Please confirm your password before continuing."
    />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirm with passkey"
        loading-label="Confirming..."
        separator="Or confirm with password"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <StallButton
                    variant="marigold"
                    size="lg"
                    type="submit"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirm password
                </StallButton>
            </div>
        </div>
    </Form>
</template>
