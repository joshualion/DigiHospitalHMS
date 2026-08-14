<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineProps({
    status: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <Head title="Verify Email" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Verify email</h1>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Please verify your email address before continuing.</p>
            <p v-if="status === 'verification-link-sent'" class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-800">A new verification link has been sent.</p>
            <div class="mt-6 flex items-center justify-between">
                <button class="rounded-md bg-red-800 px-4 py-2 text-sm font-semibold text-white" type="button" @click="router.post('/email/verification-notification')">Resend email</button>
                <Link href="/logout" method="post" as="button" class="text-sm text-red-800 dark:text-red-300">Logout</Link>
            </div>
        </section>
    </PublicLayout>
</template>
