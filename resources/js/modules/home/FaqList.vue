<script setup lang="ts">
import SectionHeading from '@/common/stall/SectionHeading.vue';

// "Questions": four native disclosures; the first is open by default. The
// plus chip turns into a cross when an item opens (200ms).
const questions = [
    {
        question: 'How does the wallet work?',
        answer: 'Every new account starts with $100 of credit. Your balance pays for orders at checkout, and you can top up from $5 to $500 at a time.',
    },
    {
        question: 'Can I shop without an account?',
        answer: 'Yes. Add anything to your basket as a guest. When you log in or sign up, your basket comes with you.',
    },
    {
        question: 'What if my balance is too low?',
        answer: 'Top up your wallet first, then place the order again. Your basket stays as it was.',
    },
    {
        question: 'Can I see what I paid before?',
        answer: 'Yes. The Orders page keeps every order with the exact prices and delivery fee you paid at the time.',
    },
];
</script>

<template>
    <section
        id="faq"
        aria-labelledby="faq-title"
        class="flex scroll-mt-6 flex-col gap-6"
    >
        <SectionHeading id="faq-title" title="Questions" />
        <div class="flex flex-col gap-3">
            <details
                v-for="(item, index) in questions"
                :key="item.question"
                :open="index === 0"
                class="group rounded-[18px] border-[1.5px] border-line bg-surface"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-[18px] px-5 py-4 text-[17px] font-extrabold text-ink sm:text-[18px] [&::-webkit-details-marker]:hidden"
                >
                    {{ item.question }}
                    <span
                        aria-hidden="true"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-paper text-xl leading-none font-bold transition-transform duration-200 ease-stall group-open:rotate-45"
                    >
                        +
                    </span>
                </summary>
                <p class="px-5 pb-5 text-base leading-relaxed text-body">
                    {{ item.answer }}
                </p>
            </details>
        </div>
    </section>
</template>
