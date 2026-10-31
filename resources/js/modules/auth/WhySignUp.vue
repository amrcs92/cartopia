<script setup lang="ts">
import Icon from '@/common/icon/Icon.vue';
import Chalkboard from '@/common/stall/Chalkboard.vue';

// The "Why sign up?" chalkboard beside every account form.
const reasons = [
    '$100 of credit when you create an account',
    'Shop as a guest; your basket comes with you when you log in',
    'Every order kept with the exact prices you paid',
];
</script>

<template>
    <Chalkboard title="Why sign up?">
        <ul class="space-y-3">
            <li v-for="reason in reasons" :key="reason" class="flex gap-3">
                <Icon
                    name="check"
                    :size="26"
                    class="mt-1 shrink-0 text-marigold"
                />
                <span>{{ reason }}</span>
            </li>
        </ul>
    </Chalkboard>
</template>
