<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/common/app/InputError.vue';
import Button from '@/common/button/Button.vue';
import Input from '@/common/input/Input.vue';
import Label from '@/common/label/Label.vue';
import { store } from '@/routes/wallet/top-ups';

const form = useForm({
    amount: '',
});

function submit(): void {
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <div class="space-y-2">
            <Label for="top-up-amount">Amount (USD)</Label>
            <Input
                id="top-up-amount"
                v-model="form.amount"
                type="number"
                inputmode="decimal"
                min="5"
                max="500"
                step="0.01"
                placeholder="25.00"
                required
            />
            <p class="text-xs text-muted-foreground">
                Between $5 and $500 per top-up.
            </p>
            <InputError :message="form.errors.amount" />
        </div>

        <Button type="submit" :disabled="form.processing" data-test="top-up">
            Add credit
        </Button>
    </form>
</template>
