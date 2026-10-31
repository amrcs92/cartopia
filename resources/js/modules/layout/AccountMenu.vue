<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import DropdownMenu from '@/common/dropdown-menu/DropdownMenu.vue';
import DropdownMenuItem from '@/common/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuLabel from '@/common/dropdown-menu/DropdownMenuLabel.vue';
import DropdownMenuSeparator from '@/common/dropdown-menu/DropdownMenuSeparator.vue';
import Icon from '@/common/icon/Icon.vue';
import IconButton from '@/common/stall/IconButton.vue';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

// The signed-in user's menu in the desktop header: their name, Account
// settings and Log out.
const props = defineProps<{
    user: User;
}>();

const itemClass =
    'min-h-11 cursor-pointer gap-3 rounded-[10px] px-3 text-[15px] font-bold focus:bg-field';
</script>

<template>
    <DropdownMenu
        align="end"
        content-class="w-60 rounded-[14px] border-[2.5px] border-outline bg-surface p-1.5 text-ink shadow-press"
    >
        <template #trigger>
            <IconButton label="Account menu" icon="user" />
        </template>

        <DropdownMenuLabel class="px-3 py-2">
            <span class="block truncate text-[15px] font-bold text-ink">
                {{ props.user.name }}
            </span>
            <span class="block truncate text-sm font-normal text-subtle">
                {{ props.user.email }}
            </span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem as-child :class="itemClass">
            <Link :href="editProfile()" class="flex w-full items-center">
                <Icon name="filters" :size="20" class="size-5 text-ink" />
                Account settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem as-child :class="[itemClass, 'text-danger']">
            <Link
                :href="logout()"
                as="button"
                data-test="logout-button"
                class="flex w-full items-center text-left"
                @click="router.flushAll()"
            >
                Log out
            </Link>
        </DropdownMenuItem>
    </DropdownMenu>
</template>
