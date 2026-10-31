<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/common/app/InputError.vue';
import PasswordInput from '@/common/app/PasswordInput.vue';
import TextLink from '@/common/app/TextLink.vue';
import StallButton from '@/common/stall/StallButton.vue';
import { Checkbox } from '@/lib/shadcn/checkbox';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import { Spinner } from '@/lib/shadcn/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/common/app/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        role="status"
        class="mb-4 rounded-xl border-2 border-green bg-chip px-4 py-3 text-sm font-bold text-chip-ink"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex min-h-11 cursor-pointer items-center gap-3 font-bold text-ink"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="size-6 rounded-md border-2 border-outline"
                    />
                    <span>Remember me</span>
                </Label>
            </div>

            <StallButton
                variant="marigold"
                size="lg"
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </StallButton>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>
</template>
