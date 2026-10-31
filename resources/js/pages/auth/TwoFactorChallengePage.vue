<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/common/app/InputError.vue';
import StallButton from '@/common/stall/StallButton.vue';
import Input from '@/common/input/Input.vue';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/lib/shadcn/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    class="size-11 text-lg"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <StallButton
                    variant="marigold"
                    size="lg"
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    >Continue</StallButton
                >
                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center font-bold text-link underline underline-offset-4 hover:no-underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    v-focus
                    required
                />
                <InputError :message="errors.recovery_code" />
                <StallButton
                    variant="marigold"
                    size="lg"
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    >Continue</StallButton
                >

                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center font-bold text-link underline underline-offset-4 hover:no-underline"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
