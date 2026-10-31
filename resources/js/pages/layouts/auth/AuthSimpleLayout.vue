<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/common/brand/AppLogo.vue';
import ThemeToggle from '@/common/theme/ThemeToggle.vue';
import WhySignUp from '@/modules/auth/WhySignUp.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-paper text-ink">
        <header
            class="mx-auto flex w-full max-w-[1200px] items-center justify-between gap-4 px-4 py-4 sm:px-6"
        >
            <Link :href="home()" aria-label="Cartopia home" class="rounded-xl">
                <AppLogo />
            </Link>
            <ThemeToggle variant="button" />
        </header>

        <main
            class="mx-auto grid w-full max-w-[1100px] flex-1 content-start items-start gap-10 px-4 pt-4 pb-12 sm:px-6 lg:grid-cols-[minmax(0,30rem)_minmax(0,1fr)] lg:gap-14 lg:pt-10"
        >
            <section
                class="rounded-[26px] border-[2.5px] border-outline bg-surface p-6 shadow-card sm:p-8"
            >
                <header class="mb-6 space-y-2">
                    <h1
                        v-if="title"
                        class="font-serif text-[28px] leading-tight text-ink sm:text-[32px]"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-base text-body">
                        {{ description }}
                    </p>
                </header>
                <slot />
            </section>

            <WhySignUp />
        </main>
    </div>
</template>
