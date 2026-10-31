<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-6 lg:space-y-8">
        <header class="space-y-2">
            <h1
                class="font-serif text-[32px] leading-tight text-ink lg:text-5xl"
            >
                Settings
            </h1>
            <p class="text-base text-body">
                Manage your profile and account settings
            </p>
        </header>

        <div
            class="grid items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-10"
        >
            <nav class="flex flex-wrap gap-2 lg:flex-col" aria-label="Settings">
                <Link
                    v-for="item in sidebarNavItems"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                    :class="
                        cn(
                            'inline-flex min-h-11 items-center rounded-xl px-4 text-base font-bold text-ink transition-colors duration-200 ease-stall hover:bg-surface',
                            isCurrentOrParentUrl(item.href) &&
                                'bg-surface shadow-[inset_0_-3px_0_var(--marigold)]',
                        )
                    "
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div
                class="max-w-2xl min-w-0 space-y-8 rounded-[26px] border-[2.5px] border-outline bg-surface p-5 shadow-card sm:p-8"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
