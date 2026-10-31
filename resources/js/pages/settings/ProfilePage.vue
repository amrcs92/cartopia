<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/common/app/DeleteUser.vue';
import Heading from '@/common/app/Heading.vue';
import InputError from '@/common/app/InputError.vue';
import Button from '@/common/button/Button.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
};

const props = defineProps<{
    profile: Profile;
}>();

const user = computed(() => props.profile);
</script>

<template>
    <Head title="Profile settings" />

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    name="phone"
                    :default-value="user.phone ?? ''"
                    autocomplete="tel"
                    placeholder="Phone number"
                />
                <InputError class="mt-2" :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input
                    id="address"
                    class="mt-1 block w-full"
                    name="address"
                    :default-value="user.address ?? ''"
                    autocomplete="street-address"
                    placeholder="Address"
                />
                <InputError class="mt-2" :message="errors.address" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
