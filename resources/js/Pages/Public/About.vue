<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head title="About" />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16">
            <h1 class="text-3xl font-bold">About HMS</h1>
            <p class="mt-4 text-slate-700 dark:text-slate-300">HMS is being recovered as a Laravel modular monolith with Inertia and Vue. This public website remains marketing content until the operational hospital modules are designed and approved.</p>
        </section>
    </PublicLayout>
</template>
