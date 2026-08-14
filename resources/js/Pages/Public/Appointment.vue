<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Appointments" />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16">
            <h1 class="text-3xl font-bold">Appointments</h1>
            <p class="mt-4 text-slate-700 dark:text-slate-300">Online appointment booking is not available in Phase 0. This page is retained only to avoid a broken public route while the real appointments module remains deferred.</p>
        </section>
    </PublicLayout>
</template>
