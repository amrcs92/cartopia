<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 font-serif text-[22px] font-normal text-ink'
                    : 'font-serif text-[28px] font-normal text-ink'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-base text-body">
            {{ description }}
        </p>
    </header>
</template>
