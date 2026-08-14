<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Doctors" />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16">
            <h1 class="text-3xl font-bold">Doctors</h1>
            <p class="mt-4 text-slate-700 dark:text-slate-300">Doctor directory content is informational for now. Staff scheduling, clinical worklists, and provider profiles are deferred to later phases.</p>
        </section>
    </PublicLayout>
</template>
