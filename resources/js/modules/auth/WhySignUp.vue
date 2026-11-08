<script setup lang="ts">
import Icon from '@/common/icon/Icon.vue';
import { computed } from 'vue';
import Chalkboard from '@/common/stall/Chalkboard.vue';
import { useSignupCredit } from '@/modules/auth/useSignupCredit';

// The "Why sign up?" chalkboard beside every account form. The credit line
// goes when admins turn the sign-up credit off.
const credit = useSignupCredit();
const reasons = computed(() => [
    ...(credit.value
        ? [`${credit.value} of credit when you create an account`]
        : []),
    'Shop as a guest; your basket comes with you when you log in',
    'Every order kept with the exact prices you paid',
]);
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
